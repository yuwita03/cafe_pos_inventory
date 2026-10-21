<?php

use App\CashOrders;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => 'cashier']));
    config(['services.midtrans.enabled' => true, 'services.midtrans.environment' => 'sandbox', 'services.midtrans.sandbox_server_key' => 'SB-Mid-server-test-secret']);
    Http::preventStrayRequests();
});

/** @return array{stock: StockItem, variant: ProductVariant, payload: array<string, mixed>} */
function qrisCheckoutFixture(): array
{
    $stock = StockItem::factory()->create(['on_hand_qty' => '30.000']);
    $variant = ProductVariant::factory()->create(['price_idr' => 25000]);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $stock->id, 'quantity' => '18.000']);

    return compact('stock', 'variant') + ['payload' => [
        'checkout_key' => (string) Str::uuid(), 'customer_name' => 'Alya',
        'fulfillment_type' => 'takeaway', 'table_id' => null,
        'payment_method' => 'qris', 'expected_total_idr' => 25000,
        'items' => [['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1]],
    ]];
}

/** @return array<string, mixed> */
function qrisChargeResponse(Request $request): array
{
    return [
        'status_code' => '201', 'transaction_status' => 'pending', 'payment_type' => 'qris',
        'order_id' => $request['transaction_details']['order_id'], 'transaction_id' => 'sandbox-transaction',
        'gross_amount' => '25000.00', 'currency' => 'IDR',
        'actions' => [['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/sandbox-transaction/qr-code']],
    ];
}

test('sandbox qris checkout persists a pending order and hold before creating one payment qr', function (string $serverKey): void {
    $this->freezeTime();
    config(['services.midtrans.sandbox_server_key' => $serverKey]);
    $fixture = qrisCheckoutFixture();
    $transactionLevel = DB::transactionLevel();
    Http::fake(function (Request $request) use ($transactionLevel) {
        expect(DB::transactionLevel())->toBe($transactionLevel);
        $this->assertDatabaseHas('payments', ['provider_reference' => $request['transaction_details']['order_id'], 'status' => 'pending', 'method' => 'qris']);
        $this->assertDatabaseCount('stock_reservations', 1);

        return Http::response(qrisChargeResponse($request), 201);
    });

    $this->get(route('admin.pos'))->assertInertia(fn (Assert $page) => $page->where('qrisAvailable', true));
    $response = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.status', 'awaiting_payment')->assertJsonPath('order.paymentStatus', 'pending')
        ->assertJsonPath('order.method', 'qris')->assertJsonPath('order.qris.state', 'ready')
        ->assertJsonPath('order.qris.qrUrl', 'https://api.sandbox.midtrans.com/v2/qris/sandbox-transaction/qr-code')
        ->assertJsonPath('order.qris.expiresAt', now()->addMinutes(15)->toIso8601String());

    $this->assertDatabaseHas('orders', ['id' => $response->json('order.id'), 'created_by_user_id' => auth()->id(), 'status' => 'awaiting_payment', 'total_idr' => 25000]);
    $this->assertDatabaseHas('stock_reservations', ['stock_item_id' => $fixture['stock']->id, 'quantity' => '18.000', 'status' => 'active', 'expires_at' => null]);
    expect($fixture['stock']->fresh()->on_hand_qty)->toBe('30.000');
    $this->assertDatabaseCount('stock_movements', 0);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode($serverKey.':'))
        && $request['payment_type'] === 'qris' && $request['transaction_details']['gross_amount'] === 25000
        && $request['custom_expiry']['expiry_duration'] === 15);
    expect($response->getContent())->not->toContain($serverKey);
})->with([
    'SB-prefixed sandbox server key' => 'SB-Mid-server-test-secret',
    'unprefixed sandbox server key' => 'Mid-server-test-secret',
]);

test('qris retries recover the original attempt without another charge or a reset deadline', function (): void {
    $this->freezeTime();
    $fixture = qrisCheckoutFixture();
    Http::fake(fn (Request $request) => Http::response(qrisChargeResponse($request), 201));
    $first = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();
    $fixture['variant']->update(['price_idr' => 99000, 'is_available' => false]);
    $this->travel(16)->minutes();
    config(['services.midtrans.enabled' => false]);

    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.id', $first->json('order.id'))
        ->assertJsonPath('order.qris.expiresAt', $first->json('order.qris.expiresAt'))
        ->assertJsonPath('order.qris.qrUrl', $first->json('order.qris.qrUrl'));
    foreach (['orders', 'payments', 'stock_reservations'] as $table) {
        $this->assertDatabaseCount($table, 1);
    }
    Http::assertSentCount(1);
});

test('overlapping qris submissions claim provider creation only once', function (): void {
    $fixture = qrisCheckoutFixture();
    Http::fake(function (Request $request) use ($fixture) {
        $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
            ->assertJsonPath('order.qris.state', 'checking');

        return Http::response(qrisChargeResponse($request), 201);
    });

    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->assertJsonPath('order.qris.state', 'ready');
    Http::assertSentCount(1);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('stock_reservations', 1);
});

test('a failed provider response is not exposed or mistaken for a definitely failed payment', function (): void {
    $fixture = qrisCheckoutFixture();
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['error' => 'SB-Mid-server-test-secret'], 500)]);
    $response = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.paymentStatus', 'pending')->assertJsonPath('order.qris.state', 'checking');
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();
    expect($response->getContent())->not->toContain('SB-Mid-server-test-secret');
    Http::assertSentCount(1);
});

test('unknown qris creation stays locked and retries never create another payable attempt', function (): void {
    $fixture = qrisCheckoutFixture();
    $calls = 0;
    Http::fake(function () use (&$calls): never {
        $calls++;
        throw new ConnectionException('Connection timed out');
    });
    $first = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.qris.state', 'checking')->assertJsonPath('order.qris.qrUrl', null);
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->assertJsonPath('order.id', $first->json('order.id'));
    $this->postJson(route('admin.pos.checkout'), [...$fixture['payload'], 'customer_name' => 'Changed'])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
    $this->postJson(route('admin.pos.checkout'), [...$fixture['payload'], 'payment_method' => 'cash', 'cash_received_idr' => 50000])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
    $this->actingAs(User::factory()->create(['role' => 'cashier']))->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
    expect($calls)->toBe(1);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseHas('payments', ['method' => 'qris', 'status' => 'pending']);
});

test('unresolved qris holds still protect stock after the payment deadline and generic expiry', function (): void {
    $this->freezeTime();
    $fixture = qrisCheckoutFixture();
    Http::fake(fn (Request $request) => Http::response(qrisChargeResponse($request), 201));
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();
    $this->travel(16)->minutes();
    app(CashOrders::class)->expirePending();

    $this->postJson(route('admin.pos.checkout'), [...$fixture['payload'], 'checkout_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->get(route('admin.pos'))->assertInertia(fn (Assert $page) => $page->where('catalog.products.0.variants.0.available', false));
    $this->actingAs(User::factory()->create(['role' => 'owner']))
        ->postJson(route('admin.inventory.adjustments'), ['stock_item_id' => $fixture['stock']->id, 'quantity_delta' => '-13.000', 'reason' => 'Count', 'source_key' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonValidationErrors('quantity_delta');
    $this->get(route('admin.kitchen'))->assertInertia(fn (Assert $page) => $page->has('orders', 0));
    $this->postJson(route('admin.kitchen.advance', Order::firstOrFail()), ['expected_status' => 'queued'])->assertUnprocessable();
    $this->assertDatabaseHas('stock_reservations', ['status' => 'active', 'expires_at' => null]);
    $this->assertDatabaseHas('orders', ['status' => 'awaiting_payment']);
    $this->assertDatabaseCount('stock_movements', 0);
    Http::assertSentCount(1);
});

test('invalid qris checkout creates no order hold or provider request', function (string $field, mixed $value, string $error): void {
    $fixture = qrisCheckoutFixture();
    $payload = $fixture['payload'];
    data_set($payload, $field, $value);
    $this->postJson(route('admin.pos.checkout'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('stock_reservations', 0);
    Http::assertNothingSent();
})->with([
    'stale price' => ['expected_total_idr', 1, 'expected_total_idr'],
    'stock shortage' => ['items.0.quantity', 2, 'expected_total_idr'],
    'missing name' => ['customer_name', '   ', 'customer_name'],
    'cash mixed into qris' => ['cash_received_idr', 50000, 'cash_received_idr'],
    'unknown method' => ['payment_method', 'card', 'payment_method'],
    'injected price' => ['items.0.unit_price_idr', 1, 'items.0'],
]);

test('sandbox qris is unavailable without an enabled sandbox configuration', function (string $setting, mixed $value): void {
    $fixture = qrisCheckoutFixture();
    config([$setting => $value]);
    $this->get(route('admin.pos'))->assertInertia(fn (Assert $page) => $page->where('qrisAvailable', false));
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    $this->assertDatabaseCount('orders', 0);
    Http::assertNothingSent();
})->with([
    'disabled' => ['services.midtrans.enabled', false],
    'production environment' => ['services.midtrans.environment', 'production'],
    'no sandbox key' => ['services.midtrans.sandbox_server_key', ''],
    'client key is not a server key' => ['services.midtrans.sandbox_server_key', 'Mid-client-test-key'],
    'prefixed client key is not a server key' => ['services.midtrans.sandbox_server_key', 'SB-Mid-client-test-key'],
    'server prefix without a secret' => ['services.midtrans.sandbox_server_key', 'Mid-server-'],
    'whitespace is not a key' => ['services.midtrans.sandbox_server_key', '   '],
]);

test('untrusted or unsuccessful charge responses remain unresolved without exposing a qr', function (string $field, mixed $value): void {
    $fixture = qrisCheckoutFixture();
    Http::fake(function (Request $request) use ($field, $value) {
        $data = qrisChargeResponse($request);
        data_set($data, $field, $value);

        return Http::response($data, 201);
    });
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.status', 'awaiting_payment')->assertJsonPath('order.qris.state', 'checking')->assertJsonPath('order.qris.qrUrl', null);
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();
    Http::assertSentCount(1);
    $this->assertDatabaseHas('stock_reservations', ['status' => 'active', 'expires_at' => null]);
})->with([
    'wrong amount' => ['gross_amount', '1.00'],
    'wrong currency' => ['currency', 'USD'],
    'wrong reference' => ['order_id', 'another-order'],
    'already paid requires later reconciliation' => ['transaction_status', 'settlement'],
    'invalid qr host' => ['actions.0.url', 'https://attacker.example/qr-code'],
    'invalid qr transaction' => ['actions.0.url', 'https://api.sandbox.midtrans.com/v2/qris/another-order/qr-code'],
]);
