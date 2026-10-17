<?php

use App\Models\CafeTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CafeTableSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create rename and deactivate a table without changing its id or orders', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $this->post(route('admin.tables.store'), ['label' => '  A01  ', 'is_active' => true])->assertSessionHasNoErrors();
    $table = CafeTable::firstOrFail();
    expect($table->label)->toBe('A01')->and(Schema::hasColumn('cafe_tables', 'qr_token'))->toBeFalse();
    $order = Order::factory()->create(['table_id' => $table->id, 'fulfillment_type' => 'dine_in']);
    config(['app.url' => 'https://cafe.example']);

    $this->get(route('admin.tables'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/tables/Index')->has('tables', 1)
        ->where('tables.0.label', 'A01')->where('tables.0.menuUrl', 'https://cafe.example/menu?table='.$table->id)
        ->missing('tables.0.qr_token'));
    $this->put(route('admin.tables.update', $table), ['label' => 'Teras 1', 'is_active' => false])->assertSessionHasNoErrors();
    expect($table->fresh()->label)->toBe('Teras 1')->and($table->fresh()->is_active)->toBeFalse()
        ->and($order->fresh()->table_id)->toBe($table->id)->and($order->fresh()->status)->toBe('queued');
    $this->get(route('admin.pos'))->assertInertia(fn (Assert $page) => $page->has('tables', 0));
    $this->put(route('admin.tables.update', $table), ['label' => 'Teras 1', 'is_active' => true])->assertSessionHasNoErrors();
    $this->get(route('admin.pos'))->assertInertia(fn (Assert $page) => $page->has('tables', 1)->where('tables.0.id', $table->id));
    $this->get(route('admin.tables'))->assertInertia(fn (Assert $page) => $page->where('tables.0.menuUrl', 'https://cafe.example/menu?table='.$table->id));
    $this->assertDatabaseCount('orders', 1);
    $this->delete(route('admin.tables.update', $table))->assertMethodNotAllowed();
});

test('invalid or duplicate table labels cannot be saved', function (mixed $label): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $table = CafeTable::factory()->create(['label' => 'A01']);
    $this->post(route('admin.tables.store'), ['label' => $label, 'is_active' => true])->assertSessionHasErrors('label');
    $this->put(route('admin.tables.update', $table), ['label' => $label === 'A01' ? '' : $label, 'is_active' => false])->assertSessionHasErrors('label');
    expect($table->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseCount('cafe_tables', 1);
})->with(['empty' => [''], 'spaces' => ['   '], 'duplicate' => ['A01'], 'too long' => [str_repeat('A', 31)], 'array' => [['A01']]]);

test('updating a table permits its own unchanged label but rejects another table label', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $first = CafeTable::factory()->create(['label' => 'A01']);
    CafeTable::factory()->create(['label' => 'A02']);
    $this->put(route('admin.tables.update', $first), ['label' => 'A01', 'is_active' => true])->assertSessionHasNoErrors();
    $this->put(route('admin.tables.update', $first), ['label' => ' A02 ', 'is_active' => true])->assertSessionHasErrors('label');
    expect($first->fresh()->label)->toBe('A01');
});

test('non owner staff cannot access or modify table management or qr output', function (string $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $table = CafeTable::factory()->create(['label' => 'A01']);
    foreach (['admin.tables.qr', 'admin.tables.print'] as $route) {
        $this->get(route($route, $table))->assertForbidden();
    }
    $this->get(route('admin.tables'))->assertForbidden();
    $this->post(route('admin.tables.store'), ['label' => 'A02', 'is_active' => true])->assertForbidden();
    $this->put(route('admin.tables.update', $table), ['label' => 'Changed', 'is_active' => false])->assertForbidden();
    expect($table->fresh()->label)->toBe('A01')->and($table->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseCount('cafe_tables', 1);
})->with(['cashier', 'kitchen']);

test('owners can preview download and print qr while inactive tables reject export', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $table = CafeTable::factory()->create();
    $response = $this->get(route('admin.tables.qr', $table))->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff');
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse()->and($xml->getName())->toBe('svg');
    $this->get(route('admin.tables.qr', ['table' => $table, 'download' => 1]))->assertDownload('meja-'.$table->id.'.svg');
    $this->get(route('admin.tables.print', $table))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/tables/Print')->where('table.id', $table->id)->where('table.label', $table->label));
    $table->update(['is_active' => false]);
    $this->get(route('admin.tables.qr', $table))->assertNotFound();
    $this->get(route('admin.tables.print', $table))->assertNotFound();
});

test('the reserved customer link serves no customer ui or catalog and writes no transactions', function (): void {
    $table = CafeTable::factory()->create();
    $product = Product::factory()->create();
    $this->get(route('guest.menu', ['table' => $table->id]))->assertStatus(503)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText('Menu pelanggan belum tersedia.')
        ->assertDontSee($product->name)->assertDontSee('data-page');
    foreach (['orders', 'payments', 'stock_reservations', 'stock_movements'] as $databaseTable) {
        $this->assertDatabaseCount($databaseTable, 0);
    }
});

test('invalid table links stay unavailable without serving customer ui or table data', function (mixed $id): void {
    $table = CafeTable::factory()->create(['is_active' => false]);
    $this->get(route('guest.menu', ['table' => $id]))->assertNotFound()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText('Meja tidak ditemukan atau sedang nonaktif.')
        ->assertDontSee($table->label)->assertDontSee('data-page');
})->with(['missing' => [null], 'unknown' => [999999], 'invalid' => ['abc'], 'negative' => [-1], 'zero' => [0], 'fraction' => ['1.5'], 'overflow' => ['999999999999999999999999999'], 'array' => [[1]]]);

test('deactivating and reactivating a table preserves the future customer link', function (): void {
    $table = CafeTable::factory()->create();
    $url = route('guest.menu', ['table' => $table->id]);
    $this->get($url)->assertStatus(503);
    $table->update(['is_active' => false]);
    $this->get($url)->assertNotFound();
    $table->update(['is_active' => true]);
    $this->get($url)->assertStatus(503);
    $this->assertDatabaseCount('orders', 0);
});

test('customer product photos are not exposed while the customer interface is deferred', function (): void {
    Storage::fake('public');
    $path = 'products/menu.png';
    Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1S8AAAAASUVORK5CYII='));
    $product = Product::factory()->create(['image_path' => $path]);
    $url = '/menu/products/'.$product->id.'/image';
    $this->get($url)->assertNotFound();
    $product->update(['is_active' => false]);
    $this->get($url)->assertNotFound();
    $this->get(route('admin.products.image', $product))->assertRedirectToRoute('login');
});

test('cafe tables are created before orders and roll back in foreign key safe order', function (): void {
    $table = CafeTable::factory()->create(['label' => 'Keep me']);
    Order::factory()->create(['table_id' => $table->id, 'fulfillment_type' => 'dine_in']);
    $tables = require database_path('migrations/2026_10_16_063432_create_cafe_tables_table.php');
    $orders = require database_path('migrations/2026_10_16_063433_create_cash_order_tables.php');
    $orders->down();
    expect(Schema::hasTable('orders'))->toBeFalse()->and($table->fresh()->label)->toBe('Keep me');
    $tables->down();
    expect(Schema::hasTable('cafe_tables'))->toBeFalse();
    $tables->up();
    $orders->up();
    expect(Schema::hasColumn('cafe_tables', 'qr_token'))->toBeFalse();
    $newTable = CafeTable::factory()->create();
    $newOrder = Order::factory()->create(['table_id' => $newTable->id, 'fulfillment_type' => 'dine_in']);
    expect($newOrder->table_id)->toBe($newTable->id);
    $this->assertDatabaseCount('cafe_tables', 1);
    $this->assertDatabaseCount('orders', 1);
});

test('development table seeding is repeatable without table tokens or changing existing status', function (): void {
    $table = CafeTable::factory()->create(['label' => 'A01', 'is_active' => false]);
    $this->seed(CafeTableSeeder::class);
    $this->seed(CafeTableSeeder::class);
    $this->assertDatabaseCount('cafe_tables', 8);
    expect($table->fresh()->is_active)->toBeFalse();
});
