<?php

use App\Http\Controllers\Admin\PurchaseController;
use App\InventoryStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($this->owner);
    $this->supplier = Supplier::factory()->create();
    $this->stock = StockItem::factory()->create(['name' => 'Croissant', 'base_unit' => 'pcs']);
    $this->payload = ['supplier_id' => $this->supplier->id, 'items' => [
        ['stock_item_id' => $this->stock->id, 'quantity' => '30.000', 'line_cost_idr' => 300000],
    ]];
});

test('owner can create edit and deactivate suppliers while preserving purchase history', function (): void {
    $this->supplier->update(['name' => 'ZZZ fixture']);
    $this->post(route('admin.suppliers.store'), ['name' => '  Bakery  ', 'contacts' => [['type' => 'phone', 'value' => '  081234  '], ['type' => 'email', 'value' => ' bakery@example.com '], ['type' => 'phone', 'value' => '  ']], 'is_active' => true])->assertSessionHasNoErrors();
    $supplier = Supplier::where('name', 'Bakery')->firstOrFail();
    $contacts = [['type' => 'phone', 'value' => '081234'], ['type' => 'email', 'value' => 'bakery@example.com']];
    expect($supplier->contact)->toBe($contacts);
    $this->get(route('admin.suppliers'))->assertInertia(fn (Assert $page) => $page->where('suppliers.0.contacts', $contacts));
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    $this->put(route('admin.suppliers.update', $supplier), ['name' => 'New Bakery', 'contacts' => [], 'is_active' => false])->assertSessionHasNoErrors();
    expect($supplier->fresh()->contact)->toBeNull();
    expect($supplier->fresh()->is_active)->toBeFalse();
    expect($purchase->fresh()->supplier_id)->toBe($supplier->id);
    $this->get(route('admin.suppliers'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/suppliers/Index')->has('suppliers', 2));
});

test('supplier input is validated', function (): void {
    $this->postJson(route('admin.suppliers.store'), ['name' => '  ', 'contacts' => [['type' => 'email', 'value' => str_repeat('x', 256)]], 'is_active' => 'bad'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'contacts.0.value', 'is_active']);
});

test('supplier contacts survive metadata edits and can expand beyond the old limit', function (): void {
    $oldContacts = [['type' => 'phone', 'value' => '081234'], ['type' => 'email', 'value' => 'old@example.com']];
    $this->supplier->update(['name' => 'Existing supplier', 'contact' => $oldContacts]);
    expect($this->supplier->fresh()->contact)->toBe($oldContacts);
    $this->get(route('admin.suppliers'))->assertInertia(fn (Assert $page) => $page->where('suppliers.0.contacts', $oldContacts));
    $this->put(route('admin.suppliers.update', $this->supplier), ['name' => 'Existing supplier', 'is_active' => true])->assertSessionHasNoErrors();
    expect($this->supplier->fresh()->contact)->toBe($oldContacts);
    $contacts = [['type' => 'email', 'value' => str_repeat('a', 60).'@'.str_repeat('b', 55).'.com'], ['type' => 'email', 'value' => str_repeat('c', 60).'@'.str_repeat('d', 55).'.com']];
    $this->put(route('admin.suppliers.update', $this->supplier), ['name' => 'Existing supplier', 'contacts' => $contacts, 'is_active' => true])->assertSessionHasNoErrors();
    expect($this->supplier->fresh()->contact)->toBe($contacts);
    expect(strlen($this->supplier->fresh()->getRawOriginal('contact')))->toBeGreaterThan(255);
    $this->get(route('admin.suppliers'))->assertInertia(fn (Assert $page) => $page->where('suppliers.0.contacts', $contacts));
});

test('supplier contacts reject malformed lists and embedded line breaks', function (mixed $contacts, string $field): void {
    $this->postJson(route('admin.suppliers.store'), ['name' => 'Invalid supplier', 'contacts' => $contacts, 'is_active' => true])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'scalar' => ['081234', 'contacts'],
    'too many' => [array_fill(0, 11, '081234'), 'contacts'],
    'non string' => [[['phone' => '081234']], 'contacts.0'],
    'newline' => [[['type' => 'phone', 'value' => "081234\nemail@example.com"]], 'contacts.0.value'],
    'unsupported type' => [[['type' => 'address', 'value' => 'Jakarta']], 'contacts.0.type'],
    'missing type' => [[['value' => '081234']], 'contacts.0.type'],
    'missing value' => [[['type' => 'phone']], 'contacts.0.value'],
    'invalid email' => [[['type' => 'email', 'value' => 'not-an-email']], 'contacts.0.value'],
    'invalid phone' => [[['type' => 'phone', 'value' => 'not-a-number']], 'contacts.0.value'],
]);

test('typed phone contacts preserve international prefixes formatting and leading zeroes', function (): void {
    $contacts = [['type' => 'phone', 'value' => '+62 (812) 3456-7890'], ['type' => 'phone', 'value' => '081234567890']];
    $this->put(route('admin.suppliers.update', $this->supplier), ['name' => $this->supplier->name, 'contacts' => $contacts, 'is_active' => true])->assertSessionHasNoErrors();
    expect($this->supplier->fresh()->contact)->toBe($contacts);
});

test('saving and editing a draft does not change stock and totals come from its lines', function (): void {
    $this->post(route('admin.purchases.store'), [...$this->payload, 'total' => 1, 'status' => 'received', 'received_by_user_id' => 999])
        ->assertSessionHasNoErrors();
    $purchase = Purchase::firstOrFail();
    expect($purchase->status)->toBe('draft');
    expect($purchase->created_by_user_id)->toBe($this->owner->id);
    expect($purchase->received_at)->toBeNull();
    expect($purchase->totalCost())->toBe(300000);
    $this->assertDatabaseCount('stock_movements', 0);
    expect($this->stock->fresh()->on_hand_qty)->toBe('0.000');
    $this->get(route('admin.purchases'))->assertInertia(fn (Assert $page) => $page->component('admin/purchases/Index')->where('purchases.0.total', 300000));
    $this->get(route('admin.purchases.create'))->assertInertia(fn (Assert $page) => $page->component('admin/purchases/Create'));
    $this->get(route('admin.purchases.edit', $purchase))->assertInertia(fn (Assert $page) => $page->component('admin/purchases/Edit')->where('purchase.items.0.quantity', '30.000'));

    $this->payload['items'][0]['quantity'] = '25.125';
    $this->payload['items'][0]['line_cost_idr'] = 250000;
    $this->put(route('admin.purchases.update', $purchase), $this->payload)->assertRedirectToRoute('admin.purchases.show', $purchase);
    expect($purchase->fresh()->totalCost())->toBe(250000);
    $this->assertDatabaseHas('purchase_items', ['purchase_id' => $purchase->id, 'quantity' => '25.125']);
    expect($this->stock->fresh()->on_hand_qty)->toBe('0.000');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('invalid purchase lines are rejected without persisting a draft', function (string $field, mixed $value, string $error): void {
    data_set($this->payload, $field, $value);
    $this->postJson(route('admin.purchases.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('purchases', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with([
    'empty items' => ['items', [], 'items'],
    'zero quantity' => ['items.0.quantity', '0', 'items.0.quantity'],
    'negative quantity' => ['items.0.quantity', '-1', 'items.0.quantity'],
    'too much precision' => ['items.0.quantity', '1.0001', 'items.0.quantity'],
    'quantity overflow' => ['items.0.quantity', '1000000000', 'items.0.quantity'],
    'negative cost' => ['items.0.line_cost_idr', -1, 'items.0.line_cost_idr'],
    'fractional cost' => ['items.0.line_cost_idr', 1.5, 'items.0.line_cost_idr'],
    'cost overflow' => ['items.0.line_cost_idr', '1000000000000', 'items.0.line_cost_idr'],
    'missing stock' => ['items.0.stock_item_id', 999999, 'items.0.stock_item_id'],
    'missing supplier' => ['supplier_id', 999999, 'supplier_id'],
    'line injection' => ['items.0.on_hand_qty', 9999, 'items.0'],
]);

test('duplicate stock items are rejected', function (): void {
    $this->payload['items'][] = $this->payload['items'][0];
    $this->postJson(route('admin.purchases.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.stock_item_id');
    $this->assertDatabaseCount('purchases', 0);
});

test('receipt adds every item once and records actor time and inventory source', function (): void {
    $milk = StockItem::factory()->create(['base_unit' => 'ml', 'on_hand_qty' => '0.875']);
    $this->payload['items'][] = ['stock_item_id' => $milk->id, 'quantity' => '10.125', 'line_cost_idr' => 0];
    $this->post(route('admin.purchases.store'), $this->payload)->assertSessionHasNoErrors();
    $purchase = Purchase::firstOrFail();
    $url = route('admin.purchases.receive', $purchase);
    $this->post($url)->assertRedirectToRoute('admin.purchases.show', $purchase)->assertSessionHasNoErrors();
    $receivedAt = $purchase->fresh()->received_at->toISOString();
    $otherOwner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($otherOwner)->post($url)->assertSessionHasNoErrors();
    expect($this->stock->fresh()->on_hand_qty)->toBe('30.000');
    expect($milk->fresh()->on_hand_qty)->toBe('11.000');
    expect($purchase->fresh()->status)->toBe('received');
    expect($purchase->fresh()->received_by_user_id)->toBe($this->owner->id);
    expect($purchase->fresh()->received_at->toISOString())->toBe($receivedAt);
    $this->assertDatabaseCount('stock_movements', 2);
    $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $this->stock->id, 'quantity_delta' => '30.000', 'actor_user_id' => $this->owner->id, 'reason' => 'Pembelian '.$purchase->reference().' diterima']);
    $this->get(route('admin.purchases.show', $purchase))->assertInertia(fn (Assert $page) => $page->component('admin/purchases/Show')->where('purchase.receiver', $this->owner->name));
    $this->get(route('admin.inventory.history'))->assertInertia(fn (Assert $page) => $page->where('stockMovements.0.source', 'Pembelian'));
});

test('purchase quantities retain their entered units without multiplying by precision', function (string $entered, string $stored): void {
    $this->stock->update(['base_unit' => 'g']);
    $this->payload['items'][0]['quantity'] = $entered;
    $this->post(route('admin.purchases.store'), $this->payload)->assertSessionHasNoErrors();
    $purchase = Purchase::firstOrFail();
    $this->assertDatabaseHas('purchase_items', ['purchase_id' => $purchase->id, 'quantity' => $stored]);
    $this->get(route('admin.purchases.show', $purchase))->assertInertia(fn (Assert $page) => $page->where('purchase.items.0.quantity', $stored));
    $this->post(route('admin.purchases.receive', $purchase))->assertSessionHasNoErrors();
    expect($this->stock->fresh()->on_hand_qty)->toBe($stored);
    $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $this->stock->id, 'quantity_delta' => $stored]);
})->with([['500', '500.000'], ['500.125', '500.125']]);

test('received purchases cannot be edited or cancelled', function (): void {
    $this->post(route('admin.purchases.store'), $this->payload);
    $purchase = Purchase::firstOrFail();
    $this->post(route('admin.purchases.receive', $purchase))->assertSessionHasNoErrors();
    $this->putJson(route('admin.purchases.update', $purchase), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson(route('admin.purchases.cancel', $purchase))->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->getJson(route('admin.purchases.edit', $purchase))->assertUnprocessable();
    $this->assertDatabaseCount('stock_movements', 1);
});

test('cancelled purchases leave stock unchanged and cannot be received or edited', function (): void {
    $this->post(route('admin.purchases.store'), $this->payload);
    $purchase = Purchase::firstOrFail();
    $this->post(route('admin.purchases.cancel', $purchase))->assertSessionHasNoErrors();
    expect($purchase->fresh()->status)->toBe('cancelled');
    $this->postJson(route('admin.purchases.receive', $purchase))->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->putJson(route('admin.purchases.update', $purchase), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseCount('stock_movements', 0);
    expect($this->stock->fresh()->on_hand_qty)->toBe('0.000');
});

test('inactive suppliers are excluded from new purchases but existing drafts remain editable and receivable', function (): void {
    $this->post(route('admin.purchases.store'), $this->payload);
    $purchase = Purchase::firstOrFail();
    $this->supplier->update(['is_active' => false]);
    $this->get(route('admin.purchases.create'))->assertInertia(fn (Assert $page) => $page->has('suppliers', 0));
    $this->postJson(route('admin.purchases.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    $this->get(route('admin.purchases.edit', $purchase))->assertInertia(fn (Assert $page) => $page->has('suppliers', 1)->where('suppliers.0.is_active', false));
    $this->put(route('admin.purchases.update', $purchase), $this->payload)->assertSessionHasNoErrors();
    $this->post(route('admin.purchases.receive', $purchase))->assertSessionHasNoErrors();
    expect($this->stock->fresh()->on_hand_qty)->toBe('30.000');
    $this->assertDatabaseCount('purchases', 1);
});

test('inactive stock cannot be newly selected but remains visible in existing drafts', function (): void {
    $this->post(route('admin.purchases.store'), $this->payload);
    $purchase = Purchase::firstOrFail();
    $this->stock->update(['is_active' => false]);
    $this->get(route('admin.purchases.create'))->assertInertia(fn (Assert $page) => $page->has('stockItems', 0));
    $this->get(route('admin.purchases.edit', $purchase))->assertInertia(fn (Assert $page) => $page->where('stockItems.0.active', false));
    $this->postJson(route('admin.purchases.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.stock_item_id');
    $this->put(route('admin.purchases.update', $purchase), $this->payload)->assertSessionHasNoErrors();
    $this->postJson(route('admin.purchases.receive', $purchase))->assertUnprocessable();
    $this->stock->update(['is_active' => true]);
    $this->post(route('admin.purchases.receive', $purchase))->assertSessionHasNoErrors();
    expect($this->stock->fresh()->on_hand_qty)->toBe('30.000');
});

test('a failing receipt rolls back earlier line movements and balances', function (string $failure): void {
    $second = StockItem::factory()->create();
    $this->payload['items'][] = ['stock_item_id' => $second->id, 'quantity' => '1.001', 'line_cost_idr' => 0];
    $this->post(route('admin.purchases.store'), $this->payload);
    $purchase = Purchase::firstOrFail();
    $second->update($failure === 'inactive' ? ['is_active' => false] : ['on_hand_qty' => '999999999.999']);
    $this->postJson(route('admin.purchases.receive', $purchase))->assertUnprocessable();
    expect($purchase->fresh()->status)->toBe('draft');
    expect($purchase->fresh()->received_at)->toBeNull();
    expect($this->stock->fresh()->on_hand_qty)->toBe('0.000');
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['inactive', 'overflow']);

test('all purchasing endpoints reject non owners and inactive staff', function (string $role, bool $active): void {
    $purchase = Purchase::factory()->create();
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
    foreach ([
        ['GET', route('admin.suppliers')], ['POST', route('admin.suppliers.store')], ['PUT', route('admin.suppliers.update', $this->supplier)],
        ['GET', route('admin.purchases')], ['GET', route('admin.purchases.create')], ['POST', route('admin.purchases.store')],
        ['GET', route('admin.purchases.show', $purchase)], ['GET', route('admin.purchases.edit', $purchase)],
        ['PUT', route('admin.purchases.update', $purchase)], ['POST', route('admin.purchases.receive', $purchase)], ['POST', route('admin.purchases.cancel', $purchase)],
    ] as [$method, $url]) {
        $this->json($method, $url)->assertStatus($active ? 403 : 401);
    }
    $this->assertDatabaseCount('stock_movements', 0);
})->with([['cashier', true], ['kitchen', true], ['owner', false]]);

test('concurrent PostgreSQL receipt attempts wait for the purchase lock and add stock once', function (): void {
    $original = DB::getDefaultConnection();
    config(['database.connections.purchasing_concurrency' => config("database.connections.$original")]);
    $connection = DB::connection('purchasing_concurrency');
    expect($connection->getDatabaseName())->toBe('cafe_pos_test');
    $owner = User::factory()->connection('purchasing_concurrency')->create(['role' => 'owner']);
    $supplier = Supplier::factory()->connection('purchasing_concurrency')->create();
    $stock = StockItem::factory()->connection('purchasing_concurrency')->create(['base_unit' => 'pcs']);
    $purchase = Purchase::factory()->connection('purchasing_concurrency')->create(['supplier_id' => $supplier->id, 'created_by_user_id' => $owner->id]);
    PurchaseItem::factory()->connection('purchasing_concurrency')->create(['purchase_id' => $purchase->id, 'stock_item_id' => $stock->id]);
    $worker = new Process([PHP_BINARY, '-r', <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (! $app->environment('testing') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'cafe_pos_test') {
            exit(2);
        }
        Illuminate\Support\Facades\DB::select("SELECT set_config('application_name', ?, false)", [getenv('PURCHASE_WORKER')]);
        $owner = App\Models\User::findOrFail(getenv('PURCHASE_OWNER'));
        $request = Illuminate\Http\Request::create('/admin/purchases', 'POST');
        $request->setUserResolver(fn () => $owner);
        $response = $app->make(App\Http\Controllers\Admin\PurchaseController::class)->receive(
            $request, App\Models\Purchase::findOrFail(getenv('PURCHASE_ID')), $app->make(App\InventoryStock::class)
        );
        echo $response->getStatusCode();
        PHP,
    ], base_path(), ['APP_ENV' => 'testing', 'PURCHASE_ID' => (string) $purchase->id, 'PURCHASE_OWNER' => (string) $owner->id, 'PURCHASE_WORKER' => 'purchase-test-'.$purchase->id], timeout: 20);

    try {
        DB::setDefaultConnection('purchasing_concurrency');
        $connection->beginTransaction();
        Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
        $worker->start();
        $deadline = microtime(true) + 10;
        do {
            $connection->select('SELECT pg_stat_clear_snapshot()');
            $waiting = $connection->selectOne("SELECT EXISTS (SELECT 1 FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock') AS waiting", ['purchase-test-'.$purchase->id])->waiting;
            if ($waiting || ! $worker->isRunning()) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        expect((bool) $waiting)->toBeTrue($worker->getErrorOutput());
        $request = Request::create('/admin/purchases', 'POST');
        $request->setUserResolver(fn (): User => $owner);
        app(PurchaseController::class)->receive($request, $purchase, app(InventoryStock::class));
        $connection->commit();
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        expect(trim($worker->getOutput()))->toBe('302');
        expect($stock->fresh()->on_hand_qty)->toBe('30.000');
        expect($purchase->fresh()->status)->toBe('received');
        expect(StockMovement::where('stock_item_id', $stock->id)->count())->toBe(1);
    } finally {
        if ($worker->isRunning()) {
            $worker->stop();
        }
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        $connection->table('stock_movements')->where('stock_item_id', $stock->id)->delete();
        $connection->table('purchase_items')->where('purchase_id', $purchase->id)->delete();
        $connection->table('purchases')->where('id', $purchase->id)->delete();
        $connection->table('stock_items')->where('id', $stock->id)->delete();
        $connection->table('suppliers')->where('id', $supplier->id)->delete();
        $connection->table('users')->where('id', $owner->id)->delete();
        DB::setDefaultConnection($original);
        DB::purge('purchasing_concurrency');
    }
});
