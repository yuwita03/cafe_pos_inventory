<?php

use App\CashOrders;
use App\MidtransQrisGateway;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function qrisCheckoutPayload(): array
{
    $table = CafeTable::factory()->create();
    $stock = StockItem::factory()->create(['on_hand_qty' => '36.000']);
    $variant = ProductVariant::factory()->create(['price_idr' => 25000]);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $stock->id, 'quantity' => '18.000']);

    return [
        'checkout_key' => (string) Str::uuid(), 'payment_method' => 'qris', 'table_id' => $table->id,
        'customer_name' => 'Alya', 'customer_email' => null, 'expected_total_idr' => 25000,
        'items' => [['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1]],
    ];
}

function qrisGateway(): void
{
    $gateway = Mockery::mock(MidtransQrisGateway::class);
    $gateway->shouldReceive('status')->andThrow(new RuntimeException('not found', 404));
    $gateway->shouldReceive('charge')->once()->andReturnUsing(function (array $payload): object {
        return (object) [
            'order_id' => $payload['transaction_details']['order_id'], 'transaction_id' => 'midtrans-transaction-1',
            'gross_amount' => '25000.00', 'currency' => 'IDR', 'payment_type' => 'qris',
            'transaction_status' => 'pending', 'status_code' => '201',
            'actions' => [(object) ['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-transaction-1/qr-code']],
        ];
    });
    app()->instance(MidtransQrisGateway::class, $gateway);
    config()->set('services.midtrans.server_key', 'sandbox-test-key');
}

/** @return array<string, string> */
function qrisNotification(Order $order, string $status = 'settlement'): array
{
    $reference = DB::table('payments')->where('order_id', $order->id)->value('provider_reference');
    $data = [
        'order_id' => $reference, 'transaction_id' => 'midtrans-transaction-1', 'gross_amount' => '25000.00',
        'currency' => 'IDR', 'payment_type' => 'qris', 'transaction_status' => $status,
        'status_code' => $status === 'settlement' ? '200' : '202', 'fraud_status' => 'accept',
    ];
    $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].'sandbox-test-key');

    return $data;
}

test('guest QRIS checkout keeps one pending order and shows its QR after refresh', function (): void {
    qrisGateway();
    $payload = qrisCheckoutPayload();
    $first = $this->postJson(route('guest.orders.store'), $payload)->assertOk()
        ->assertJsonPath('order.method', 'qris')->assertJsonPath('order.status', 'awaiting_payment')
        ->assertJsonPath('order.qrImageUrl', 'https://api.sandbox.midtrans.com/v2/qris/midtrans-transaction-1/qr-code');
    $order = Order::firstOrFail();
    $this->postJson(route('guest.orders.store'), $payload)->assertOk()->assertJsonPath('order.id', $order->id);
    $this->assertDatabaseCount('payments', 1);
    $this->get($first->json('statusUrl'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.qrImageUrl', $first->json('order.qrImageUrl')));
    expect($order->status)->toBe('awaiting_payment');
});

test('new QRIS checkout cannot reuse a settlement from a previous database', function (): void {
    config()->set('services.midtrans.server_key', 'sandbox-test-key');
    $gateway = Mockery::mock(MidtransQrisGateway::class);
    $gateway->shouldReceive('status')->andReturnUsing(function (string $reference): object {
        if (! preg_match('/^CAFE-\d+$/', $reference)) {
            throw new RuntimeException('not found', 404);
        }

        return (object) [
            'order_id' => $reference, 'transaction_id' => 'previous-database-transaction',
            'gross_amount' => '25000.00', 'currency' => 'IDR', 'payment_type' => 'qris',
            'transaction_status' => 'settlement', 'status_code' => '200', 'fraud_status' => 'accept',
        ];
    });
    $gateway->shouldReceive('charge')->once()
        ->withArgs(fn (array $payload, string $idempotencyKey): bool => $idempotencyKey === $payload['transaction_details']['order_id'])
        ->andReturnUsing(fn (array $payload): object => (object) [
            'order_id' => $payload['transaction_details']['order_id'], 'transaction_id' => 'new-database-transaction',
            'gross_amount' => '25000.00', 'currency' => 'IDR', 'payment_type' => 'qris',
            'transaction_status' => 'pending', 'status_code' => '201',
        ]);
    app()->instance(MidtransQrisGateway::class, $gateway);

    $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk()
        ->assertJsonPath('order.paymentStatus', 'pending')->assertJsonPath('order.status', 'awaiting_payment')
        ->assertJsonPath('order.qrImageUrl', 'https://api.sandbox.midtrans.com/v2/qris/new-database-transaction/qr-code');
});

test('verified duplicate settlement queues a QRIS order once and rejects forged notification', function (): void {
    qrisGateway();
    $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk();
    $order = Order::firstOrFail();
    $notification = qrisNotification($order);
    $this->postJson(route('payments.midtrans.notification'), [...$notification, 'signature_key' => 'forged'])->assertBadRequest();
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
    $this->postJson(route('payments.midtrans.notification'), $notification)->assertOk();
    $this->postJson(route('payments.midtrans.notification'), $notification)->assertOk();
    expect($order->fresh()->status)->toBe('queued');
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'active', 'expires_at' => null]);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->actingAs(User::factory()->create(['role' => 'kitchen']))->get(route('admin.kitchen'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 1));
});

test('configured Midtrans callback path accepts a signed settlement', function (): void {
    qrisGateway();
    $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk();
    $order = Order::firstOrFail();

    $this->postJson('/api/midtrans/notification', qrisNotification($order))->assertOk();

    expect($order->fresh()->status)->toBe('queued');
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
});

test('settlement after local expiry goes to payment review without kitchen entry', function (): void {
    $this->freezeTime();
    qrisGateway();
    $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk();
    $order = Order::firstOrFail();
    $this->travel(31)->minutes();
    app(CashOrders::class)->expirePending();
    $this->postJson(route('payments.midtrans.notification'), qrisNotification($order))->assertOk();
    expect($order->fresh()->status)->toBe('payment_review');
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'released']);
    $this->actingAs(User::factory()->create(['role' => 'kitchen']))->get(route('admin.kitchen'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 0));
});

test('failed QRIS notification releases the hold and amount mismatch cannot confirm payment', function (): void {
    qrisGateway();
    $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk();
    $order = Order::firstOrFail();
    $mismatch = qrisNotification($order);
    $mismatch['gross_amount'] = '26000.00';
    $mismatch['signature_key'] = hash('sha512', $mismatch['order_id'].$mismatch['status_code'].$mismatch['gross_amount'].'sandbox-test-key');
    $this->postJson(route('payments.midtrans.notification'), $mismatch)->assertBadRequest();
    expect($order->fresh()->status)->toBe('awaiting_payment');
    $this->postJson(route('payments.midtrans.notification'), qrisNotification($order, 'deny'))->assertOk();
    expect($order->fresh()->status)->toBe('cancelled');
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'released']);
});

test('retry recovers a pending QRIS charge without creating another payment', function (): void {
    config()->set('services.midtrans.server_key', 'sandbox-test-key');
    $gateway = Mockery::mock(MidtransQrisGateway::class);
    $gateway->shouldReceive('status')->once()->andThrow(new RuntimeException('network unavailable'));
    $gateway->shouldReceive('status')->once()->andReturnUsing(fn (string $reference): object => (object) [
        'order_id' => $reference, 'transaction_id' => 'midtrans-transaction-1', 'gross_amount' => '25000.00',
        'currency' => 'IDR', 'payment_type' => 'qris', 'transaction_status' => 'pending', 'status_code' => '201',
    ]);
    $gateway->shouldNotReceive('charge');
    app()->instance(MidtransQrisGateway::class, $gateway);
    $first = $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk()->assertJsonPath('order.qrImageUrl', null);
    $order = Order::firstOrFail();
    $this->post(route('guest.orders.retry-qris', ['order' => $order->public_token]))->assertRedirect($first->json('statusUrl'));
    $this->assertDatabaseCount('payments', 1);
    $this->get($first->json('statusUrl'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.qrImageUrl', 'https://api.sandbox.midtrans.com/v2/qris/midtrans-transaction-1/qr-code'));
});

test('guest can verify an existing QRIS charge after a missed notification', function (): void {
    qrisGateway();
    $response = $this->postJson(route('guest.orders.store'), qrisCheckoutPayload())->assertOk();
    $order = Order::firstOrFail();

    $gateway = Mockery::mock(MidtransQrisGateway::class);
    $gateway->shouldReceive('status')->once()->andReturn((object) qrisNotification($order));
    app()->instance(MidtransQrisGateway::class, $gateway);

    $this->post($response->json('statusUrl').'/retry-qris')->assertRedirect();

    expect($order->fresh()->status)->toBe('queued');
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
});

test('cashier QRIS checkout waits for payment and does not require cash received', function (): void {
    qrisGateway();
    $payload = qrisCheckoutPayload();
    $payload['fulfillment_type'] = 'dine_in';
    $this->actingAs(User::factory()->create(['role' => 'cashier']))->postJson(route('admin.pos.checkout'), $payload)
        ->assertOk()->assertJsonPath('order.method', 'qris')->assertJsonPath('order.status', 'awaiting_payment');
    $order = Order::firstOrFail();
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'cash_received_idr' => null]);
});
