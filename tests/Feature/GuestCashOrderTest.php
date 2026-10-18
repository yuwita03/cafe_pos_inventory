<?php

use App\CashOrders;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{table: CafeTable, variant: ProductVariant, stock: StockItem, payload: array<string, mixed>} */
function guestCashFixture(string $onHand = '50.000'): array
{
    $table = CafeTable::factory()->create();
    $stock = StockItem::factory()->create(['on_hand_qty' => $onHand]);
    $variant = ProductVariant::factory()->create(['price_idr' => 25000]);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $stock->id, 'quantity' => '18.000']);

    return compact('table', 'stock', 'variant') + ['payload' => [
        'checkout_key' => (string) Str::uuid(), 'table_id' => $table->id,
        'customer_name' => 'Alya', 'customer_email' => 'alya@example.test',
        'expected_total_idr' => 25000,
        'items' => [['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1]],
    ]];
}

test('guest checkout creates one pending cash order with a private receipt and thirty minute hold', function (): void {
    $this->freezeTime();
    $fixture = guestCashFixture();
    $first = $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.status', 'awaiting_payment')
        ->assertJsonPath('order.paymentStatus', 'pending')
        ->assertJsonPath('order.customerEmail', 'alya@example.test');
    $order = Order::firstOrFail();
    $number = 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    expect($first->json('order.number'))->toBe($number);
    expect($first->json('statusUrl'))->toBe(route('guest.orders.show', ['order' => $order->public_token], false));
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'source' => 'table_qr', 'created_by_user_id' => null, 'customer_email' => 'alya@example.test']);
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'cash', 'status' => 'pending', 'cash_received_idr' => null]);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'active', 'expires_at' => now()->addMinutes(30)]);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->get($first->json('statusUrl'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('guest/OrderStatus')->where('order.number', $number)->where('order.paymentStatus', 'pending'));
    $this->get('/menu/orders/'.$order->id)->assertNotFound();

    $fixture['variant']->update(['price_idr' => 99000]);
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertOk()->assertJsonPath('order.id', $order->id);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
});

test('cashier confirmation pays an existing guest order once and sends it to kitchen', function (): void {
    $fixture = guestCashFixture();
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertOk();
    $order = Order::firstOrFail();
    $number = 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $this->actingAs($cashier)->get(route('admin.orders', ['order' => $number]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedOrder.id', $order->id));
    $this->post(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 50000])->assertRedirect();
    $this->post(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 50000])->assertRedirect();
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid', 'cash_received_idr' => 50000, 'change_idr' => 25000]);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'active', 'expires_at' => null]);
    $this->assertDatabaseCount('payments', 1);
    expect($order->fresh()->status)->toBe('queued');
    $this->actingAs(User::factory()->create(['role' => 'kitchen']))->get(route('admin.kitchen'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.id', $order->id));
    $this->get(route('guest.orders.show', ['order' => $order->public_token]))->assertInertia(fn (Assert $page) => $page->where('order.paymentStatus', 'paid'));
});

test('expired guest cash orders release stock and cannot be paid', function (): void {
    $this->freezeTime();
    $fixture = guestCashFixture('18.000');
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertOk();
    $order = Order::firstOrFail();
    $this->postJson(route('guest.orders.store'), [...$fixture['payload'], 'checkout_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->travel(31)->minutes();
    $this->actingAs(User::factory()->create(['role' => 'cashier']))
        ->postJson(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 25000])->assertUnprocessable();
    $this->get(route('guest.orders.show', ['order' => $order->public_token]))->assertInertia(fn (Assert $page) => $page
        ->where('order.status', 'cancelled')->where('order.paymentStatus', 'expired'));
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'released']);
    $this->postJson(route('guest.orders.store'), [...$fixture['payload'], 'checkout_key' => (string) Str::uuid()])->assertOk();
    expect(Order::count())->toBe(2);
});

test('scheduled expiry releases an abandoned guest cash order', function (): void {
    $this->freezeTime();
    $fixture = guestCashFixture();
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertOk();
    $order = Order::firstOrFail();
    $this->travel(31)->minutes();
    app(CashOrders::class)->expirePending();
    expect($order->fresh()->status)->toBe('cancelled');
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'expired']);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'status' => 'released']);
});

test('guest cash checkout rejects invalid email and changed totals', function (array $changes): void {
    $fixture = guestCashFixture();
    $this->postJson(route('guest.orders.store'), [...$fixture['payload'], ...$changes])->assertUnprocessable();
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
})->with([
    'invalid email' => [['customer_email' => 'not-an-email']],
    'changed total' => [['expected_total_idr' => 1]],
]);

test('a guest or kitchen worker cannot confirm guest cash', function (): void {
    $fixture = guestCashFixture();
    $this->postJson(route('guest.orders.store'), [...$fixture['payload'], 'status' => 'paid', 'cash_received_idr' => 25000])->assertOk();
    $order = Order::firstOrFail();
    $this->post(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 25000])->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create(['role' => 'kitchen']))
        ->post(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 25000])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'cashier']))
        ->postJson(route('admin.orders.confirm-cash', $order), ['cash_received_idr' => 1])->assertUnprocessable();
    expect(DB::table('payments')->where('order_id', $order->id)->value('status'))->toBe('pending');
});

test('an inactive table cannot create a guest cash order', function (): void {
    $fixture = guestCashFixture();
    $fixture['table']->update(['is_active' => false]);
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertUnprocessable()->assertJsonValidationErrors('table_id');
    $this->assertDatabaseCount('orders', 0);
});

test('direct customer and cashier checkout reject unconfigured inactive or insufficient ingredient stock', function (string $configuration): void {
    $fixture = guestCashFixture('0.000');
    if ($configuration === 'missing') {
        $fixture['variant']->recipeItems()->delete();
    } elseif ($configuration === 'inactive') {
        $fixture['stock']->update(['is_active' => false]);
    }
    $field = $configuration === 'zero' ? 'items' : 'variant_id';
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->actingAs(User::factory()->create(['role' => 'cashier']))->postJson(route('admin.pos.checkout'), $fixture['payload'] + [
        'fulfillment_type' => 'dine_in', 'cash_received_idr' => 50000,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('stock_reservations', 0);
})->with(['missing', 'inactive', 'zero']);

test('stock depleted after loading the menu returns a product specific cart error without creating an order', function (): void {
    $fixture = guestCashFixture();
    $this->get(route('guest.menu', ['table' => $fixture['table']->id]))->assertInertia(fn (Assert $page) => $page
        ->where('catalog.products.0.variants.0.available', true));
    $fixture['stock']->update(['on_hand_qty' => 0]);
    $productName = $fixture['variant']->product->name;
    $this->postJson(route('guest.orders.store'), $fixture['payload'])->assertUnprocessable()
        ->assertJsonPath('errors.items.0', "Stok untuk {$productName} tidak lagi mencukupi. Silakan periksa keranjang.");
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('stock_reservations', 0);
});
