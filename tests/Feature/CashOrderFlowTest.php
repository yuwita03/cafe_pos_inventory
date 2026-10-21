<?php

use App\Models\CafeTable;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\OptionIngredient;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
});

/** @return array{variant: ProductVariant, stock: StockItem, payload: array<string, mixed>} */
function cashOrderFixture(string $onHand = '100.000'): array
{
    $stock = StockItem::factory()->create(['on_hand_qty' => $onHand]);
    $variant = ProductVariant::factory()->create(['price_idr' => 25000]);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $stock->id, 'quantity' => '18.125']);

    return ['variant' => $variant, 'stock' => $stock, 'payload' => [
        'checkout_key' => (string) Str::uuid(), 'customer_name' => 'Alya',
        'fulfillment_type' => 'takeaway', 'table_id' => null,
        'expected_total_idr' => 25000, 'cash_received_idr' => 50000,
        'items' => [['variant_id' => $variant->id, 'option_ids' => [], 'quantity' => 1]],
    ]];
}

test('cash checkout saves priced snapshots and aggregates ingredient option and pastry reservations', function (): void {
    $this->freezeTime();
    $fixture = cashOrderFixture();
    $variant = $fixture['variant'];
    $variant->product->update(['name' => 'Americano']);
    $variant->update(['name' => 'Iced']);
    $milk = StockItem::factory()->create(['base_unit' => 'ml', 'on_hand_qty' => '1000.000']);
    RecipeItem::factory()->create(['product_variant_id' => $variant->id, 'stock_item_id' => $milk->id, 'quantity' => '120.125']);
    $group = OptionGroup::factory()->create(['name' => 'Extra shot']);
    $option = Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Double', 'price_delta_idr' => 5000]);
    $variant->product->optionGroups()->attach($group->id, ['is_required' => true]);
    OptionIngredient::factory()->create(['option_id' => $option->id, 'stock_item_id' => $fixture['stock']->id, 'additional_qty' => '2.125']);
    $pastryStock = StockItem::factory()->create(['base_unit' => 'pcs', 'on_hand_qty' => '10.000']);
    $pastry = ProductVariant::factory()->create(['stock_item_id' => $pastryStock->id, 'price_idr' => 20000]);
    $table = CafeTable::factory()->create();
    $payload = [...$fixture['payload'], 'fulfillment_type' => 'dine_in', 'table_id' => $table->id,
        'expected_total_idr' => 120000, 'cash_received_idr' => 150000,
        'items' => [
            ['variant_id' => $variant->id, 'option_ids' => [$option->id], 'quantity' => 2],
            ['variant_id' => $pastry->id, 'option_ids' => [], 'quantity' => 3],
        ]];

    $response = $this->postJson(route('admin.pos.checkout'), $payload)->assertOk()
        ->assertJsonPath('order.status', 'queued')->assertJsonPath('order.paymentStatus', 'paid')
        ->assertJsonPath('order.method', 'cash')->assertJsonPath('order.received', 150000)->assertJsonPath('order.change', 30000);

    $orderId = $response->json('order.id');
    $this->assertDatabaseHas('orders', ['id' => $orderId, 'source' => 'cashier', 'status' => 'queued', 'table_id' => $table->id, 'created_by_user_id' => auth()->id(), 'total_idr' => 120000]);
    $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'cash', 'status' => 'paid', 'amount_idr' => 120000, 'paid_at' => now(), 'cash_received_idr' => 150000, 'change_idr' => 30000]);
    $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_variant_id' => $variant->id, 'product_name_snapshot' => 'Americano', 'variant_name_snapshot' => 'Iced', 'unit_price_idr' => 30000, 'quantity' => 2]);
    $this->assertDatabaseHas('order_item_options', ['option_id' => $option->id, 'option_name_snapshot' => 'Double', 'price_delta_idr' => 5000]);
    foreach ([$fixture['stock']->id => '40.500', $milk->id => '240.250', $pastryStock->id => '3.000'] as $stockId => $quantity) {
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $orderId, 'stock_item_id' => $stockId, 'quantity' => $quantity, 'status' => 'active', 'expires_at' => null]);
    }
    $this->assertDatabaseCount('stock_reservations', 3);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '100.000']);
    $this->assertDatabaseHas('stock_items', ['id' => $milk->id, 'on_hand_qty' => '1000.000']);
    $this->assertDatabaseHas('stock_items', ['id' => $pastryStock->id, 'on_hand_qty' => '10.000']);
});

test('checkout retry returns the original paid order after the menu changes without duplicating anything', function (): void {
    $fixture = cashOrderFixture();
    $first = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();
    $fixture['variant']->product->update(['is_active' => false, 'name' => 'Changed']);
    $fixture['variant']->update(['price_idr' => 99000, 'is_available' => false]);

    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()
        ->assertJsonPath('order.id', $first->json('order.id'))->assertJsonPath('order.number', $first->json('order.number'));

    foreach (['orders', 'payments', 'order_items', 'stock_reservations'] as $table) {
        $this->assertDatabaseCount($table, 1);
    }
    $this->assertDatabaseHas('orders', ['total_idr' => 25000]);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('a shared required option is valid across distinct cart lines and stock aggregates per order', function (): void {
    $fixture = cashOrderFixture();
    $group = OptionGroup::factory()->create();
    $option = Option::factory()->create(['option_group_id' => $group->id, 'price_delta_idr' => 5000]);
    $first = $fixture['variant'];
    $second = ProductVariant::factory()->create(['price_idr' => 20000]);
    RecipeItem::factory()->create(['product_variant_id' => $second->id, 'stock_item_id' => $fixture['stock']->id, 'quantity' => '10.000']);
    foreach ([$first, $second] as $variant) {
        $variant->product->optionGroups()->attach($group->id, ['is_required' => true]);
    }
    OptionIngredient::factory()->create(['option_id' => $option->id, 'stock_item_id' => $fixture['stock']->id, 'additional_qty' => '2.125']);
    $payload = [...$fixture['payload'], 'expected_total_idr' => 80000, 'cash_received_idr' => 100000,
        'items' => [
            ['variant_id' => $first->id, 'option_ids' => [$option->id], 'quantity' => 1],
            ['variant_id' => $second->id, 'option_ids' => [$option->id], 'quantity' => 2],
        ]];

    $orderId = $this->postJson(route('admin.pos.checkout'), $payload)->assertOk()->json('order.id');

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'total_idr' => 80000]);
    $this->assertDatabaseCount('order_items', 2);
    $this->assertDatabaseCount('order_item_options', 2);
    $this->assertDatabaseCount('stock_reservations', 1);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $orderId, 'stock_item_id' => $fixture['stock']->id, 'quantity' => '44.500', 'status' => 'active']);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('reused checkout keys return 422 for changed payloads and other cashiers', function (): void {
    $fixture = cashOrderFixture();
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();

    $this->postJson(route('admin.pos.checkout'), [...$fixture['payload'], 'customer_name' => 'Bima'])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
    $this->actingAs(User::factory()->create(['role' => 'cashier']))->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertUnprocessable()->assertJsonValidationErrors('checkout_key');

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('stock_reservations', 1);
});

test('invalid cash checkouts return 422 without creating orders payments or reservations', function (string $field, mixed $value, string $error): void {
    $fixture = cashOrderFixture();
    $payload = $fixture['payload'];
    data_set($payload, $field, $value);

    $this->postJson(route('admin.pos.checkout'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);

    foreach (['orders', 'payments', 'order_items', 'stock_reservations', 'stock_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '100.000']);
})->with([
    'insufficient cash' => ['cash_received_idr', 24999, 'cash_received_idr'],
    'fractional cash' => ['cash_received_idr', 50000.5, 'cash_received_idr'],
    'stale total' => ['expected_total_idr', 1, 'expected_total_idr'],
    'missing customer' => ['customer_name', '   ', 'customer_name'],
    'dine in without table' => ['fulfillment_type', 'dine_in', 'table_id'],
    'unknown variant' => ['items.0.variant_id', 999999, 'items.0.variant_id'],
    'zero quantity' => ['items.0.quantity', 0, 'items.0.quantity'],
    'injected price' => ['items.0.unit_price_idr', 1, 'items.0'],
]);

test('required choices and foreign options return 422 with no partial checkout', function (string $case): void {
    $fixture = cashOrderFixture();
    $group = OptionGroup::factory()->create();
    $option = Option::factory()->create(['option_group_id' => $group->id]);
    $fixture['variant']->product->optionGroups()->attach($group->id, ['is_required' => true]);
    $payload = $fixture['payload'];
    if ($case === 'foreign option') {
        $payload['items'][0]['option_ids'] = [Option::factory()->create()->id];
    }
    if ($case === 'inactive option') {
        $option->update(['is_active' => false]);
        $payload['items'][0]['option_ids'] = [$option->id];
    }
    if ($case === 'duplicate option within one line') {
        $payload['items'][0]['option_ids'] = [$option->id, $option->id];
    }

    $this->postJson(route('admin.pos.checkout'), $payload)->assertUnprocessable();

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('stock_reservations', 0);
})->with(['missing required option', 'foreign option', 'inactive option', 'duplicate option within one line']);

test('active reservations block another checkout and negative stock adjustments from overselling', function (): void {
    $fixture = cashOrderFixture('30.000');
    $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk();

    $this->postJson(route('admin.pos.checkout'), [...$fixture['payload'], 'checkout_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->postJson(route('admin.inventory.adjustments'), ['stock_item_id' => $fixture['stock']->id, 'quantity_delta' => '-12.000', 'reason' => 'Physical count', 'source_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('quantity_delta');

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('stock_reservations', 1);
    $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '30.000']);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->get(route('admin.inventory.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('stockItems.0.reserved', 18.125)->where('stockItems.0.available', 11.875));
});

test('a shortage on a later cart line rolls back the entire checkout', function (): void {
    $fixture = cashOrderFixture();
    $empty = StockItem::factory()->create(['base_unit' => 'pcs']);
    $pastry = ProductVariant::factory()->create(['stock_item_id' => $empty->id]);
    $payload = [...$fixture['payload'], 'expected_total_idr' => 50000,
        'items' => [...$fixture['payload']['items'], ['variant_id' => $pastry->id, 'option_ids' => [], 'quantity' => 1]]];

    $this->postJson(route('admin.pos.checkout'), $payload)->assertUnprocessable();

    foreach (['orders', 'payments', 'order_items', 'stock_reservations', 'stock_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

test('kitchen consumes checkout recipe snapshots once and later stages do not deduct again', function (): void {
    $fixture = cashOrderFixture();
    $orderId = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order.id');
    $fixture['variant']->recipeItems()->update(['quantity' => '99.000']);
    $fixture['variant']->update(['is_available' => false]);
    $fixture['variant']->product->update(['name' => 'Changed later', 'is_active' => false]);
    $kitchen = User::factory()->create(['role' => 'kitchen']);
    $this->actingAs($kitchen);

    foreach (['queued' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'] as $expected => $result) {
        $this->post(route('admin.kitchen.advance', $orderId), ['expected_status' => $expected])->assertSessionHasNoErrors();
        $this->post(route('admin.kitchen.advance', $orderId), ['expected_status' => $expected])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => $result]);
        $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '81.875']);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    $this->assertDatabaseHas('stock_reservations', ['order_id' => $orderId, 'quantity' => '18.125', 'status' => 'consumed']);
    $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $fixture['stock']->id, 'quantity_delta' => '-18.125', 'actor_user_id' => $kitchen->id]);
    expect(StockMovement::firstOrFail()->reason)->toContain((string) $orderId);
    $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'status' => 'paid']);
});

test('kitchen rejects skipped and stale transitions without altering stock', function (): void {
    $fixture = cashOrderFixture();
    $orderId = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order.id');

    $this->postJson(route('admin.kitchen.advance', $orderId), ['expected_status' => 'ready'])->assertUnprocessable();
    $this->post(route('admin.kitchen.advance', $orderId), ['expected_status' => 'queued'])->assertSessionHasNoErrors();
    $this->post(route('admin.kitchen.advance', $orderId), ['expected_status' => 'preparing'])->assertSessionHasNoErrors();
    $this->postJson(route('admin.kitchen.advance', $orderId), ['expected_status' => 'queued'])->assertUnprocessable();

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'ready']);
    $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '81.875']);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('stock reserved by a paid queued order cannot be deactivated and remains usable by kitchen', function (): void {
    $fixture = cashOrderFixture();
    $orderId = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order.id');
    $stock = $fixture['stock'];

    $this->put(route('admin.inventory.items.update', $stock), ['name' => $stock->name, 'base_unit' => $stock->base_unit, 'reorder_point' => '0', 'is_active' => false])->assertSessionHasErrors('is_active');
    $this->assertDatabaseHas('stock_items', ['id' => $stock->id, 'is_active' => true, 'on_hand_qty' => '100.000']);
    $this->actingAs(User::factory()->create(['role' => 'kitchen']))->post(route('admin.kitchen.advance', $orderId), ['expected_status' => 'queued'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'preparing']);
    $this->assertDatabaseHas('stock_items', ['id' => $stock->id, 'on_hand_qty' => '81.875']);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $orderId, 'status' => 'consumed']);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('unpaid orders cannot enter preparation or consume held stock', function (): void {
    $fixture = cashOrderFixture();
    $orderId = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order.id');
    DB::table('payments')->where('order_id', $orderId)->update(['status' => 'pending', 'paid_at' => null]);

    $this->postJson(route('admin.kitchen.advance', $orderId), ['expected_status' => 'queued'])->assertUnprocessable();

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'queued']);
    $this->assertDatabaseHas('stock_reservations', ['order_id' => $orderId, 'status' => 'active']);
    $this->assertDatabaseHas('stock_items', ['id' => $fixture['stock']->id, 'on_hand_qty' => '100.000']);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('orders and kitchen read persisted transactions without mock rows across repeated reads', function (): void {
    foreach (['admin.orders' => 'admin/orders/Index', 'admin.kitchen' => 'admin/kitchen/Index'] as $route => $component) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->has('orders', 0));
    }
    $fixture = cashOrderFixture();
    $orderId = $this->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order.id');

    foreach (['admin.orders', 'admin.kitchen', 'admin.orders'] as $route) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.id', $orderId)->where('orders.0.customerName', 'Alya')->where('orders.0.paymentStatus', 'paid')->where('orders.0.status', 'queued'));
    }
});

test('cashiers can checkout and read shared orders but cannot operate kitchen', function (): void {
    $fixture = cashOrderFixture();
    $creator = User::factory()->create(['role' => 'cashier']);
    $foreignOrder = $this->actingAs($creator)->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertOk()->json('order');
    $cashier = User::factory()->create(['role' => 'cashier']);
    $payload = [...$fixture['payload'], 'checkout_key' => (string) Str::uuid()];
    $orderId = $this->actingAs($cashier)->postJson(route('admin.pos.checkout'), $payload)->assertOk()->json('order.id');

    $this->get(route('admin.orders'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 2)->where('summary.total', 2)->where('pagination.total', 2));
    $this->get(route('admin.orders', ['order' => $foreignOrder['number']]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedOrder.id', $foreignOrder['id']));
    $this->get(route('admin.orders', ['order' => 'ORD-'.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT)]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedOrder.id', $orderId));
    $this->postJson(route('admin.kitchen.advance', $orderId), ['expected_status' => 'queued'])->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'queued', 'created_by_user_id' => $cashier->id]);
    $this->assertDatabaseHas('orders', ['id' => $foreignOrder['id'], 'status' => 'queued', 'created_by_user_id' => $creator->id]);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('orders search and independent filters limit shared summaries and pagination', function (string $role): void {
    $creator = User::factory()->create(['role' => 'cashier']);
    $target = Order::factory()->create(['created_by_user_id' => $creator->id, 'customer_name' => 'Needle customer', 'status' => 'completed']);
    $pending = Order::factory()->create(['created_by_user_id' => $creator->id, 'customer_name' => 'Needle unpaid', 'status' => 'completed']);
    $queued = Order::factory()->create(['created_by_user_id' => $creator->id, 'customer_name' => 'Needle queued', 'status' => 'queued']);
    $qr = Order::factory()->create(['created_by_user_id' => null, 'customer_name' => 'Needle QR', 'status' => 'completed', 'source' => 'table_qr', 'fulfillment_type' => 'dine_in', 'table_id' => CafeTable::factory()->create()->id]);
    Order::factory()->count(26)->create(['created_by_user_id' => $creator->id, 'customer_name' => 'Other customer']);
    foreach ([$target, $pending, $queued, $qr] as $order) {
        DB::table('payments')->insert(['order_id' => $order->id, 'method' => 'cash', 'amount_idr' => 25000, 'status' => $order->id === $pending->id ? 'pending' : 'paid', 'cash_received_idr' => 25000, 'change_idr' => 0, 'paid_at' => $order->id === $pending->id ? null : now(), 'created_at' => now()]);
    }
    $filters = ['q' => 'needle', 'status' => 'completed', 'payment_status' => 'paid', 'source' => 'cashier'];
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(route('admin.orders', $filters))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('orders', 1)->where('orders.0.id', $target->id)->where('filters', $filters)
        ->where('summary.total', 1)->where('summary.pending', 0)->where('pagination.total', 1));
    $number = 'ORD-'.str_pad((string) $target->id, 6, '0', STR_PAD_LEFT);
    $this->get(route('admin.orders', ['q' => $number]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 1)->where('orders.0.id', $target->id));
    $this->get(route('admin.orders'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 25)->where('summary.total', 30)->where('summary.pending', 1)->where('pagination.total', 30)->where('pagination.current_page', 1)->where('pagination.last_page', 2));
    $this->get(route('admin.orders', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders', 5)->where('pagination.current_page', 2)->where('pagination.from', 26)->where('pagination.to', 30));
})->with(['owner', 'cashier']);

test('kitchen staff cannot create cash sales', function (): void {
    $fixture = cashOrderFixture();

    $this->actingAs(User::factory()->create(['role' => 'kitchen']))->postJson(route('admin.pos.checkout'), $fixture['payload'])->assertForbidden();

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('stock_reservations', 0);
});
