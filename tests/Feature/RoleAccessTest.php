<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('each role receives its authorized screens and server capabilities', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user);
    $screens = ['admin.dashboard', 'admin.menu', 'admin.categories', 'admin.options', 'admin.inventory.index',
        'admin.inventory.recipes', 'admin.inventory.history', 'admin.suppliers', 'admin.purchases', 'admin.tables', 'admin.staff'];
    foreach ($screens as $screen) {
        $response = $this->get(route($screen));
        $role === 'owner' ? $response->assertOk() : $response->assertForbidden();
    }
    foreach (['admin.pos', 'admin.orders', 'admin.kitchen'] as $screen) {
        $allowed = $role === 'owner' || ($screen === 'admin.kitchen' ? $role === 'kitchen' : $role === 'cashier');
        $response = $this->get(route($screen));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
    $this->get(route($user->landingRoute()))->assertInertia(fn (Assert $page) => $page
        ->where('auth.capabilities.manage-workspace', $role === 'owner')
        ->where('auth.capabilities.manage-staff', $role === 'owner')
        ->where('auth.capabilities.manage-purchasing', $role === 'owner')
        ->where('auth.capabilities.use-pos', in_array($role, ['owner', 'cashier'], true))
        ->where('auth.capabilities.use-kitchen', in_array($role, ['owner', 'kitchen'], true)));
})->with(['owner', 'cashier', 'kitchen']);

test('every forbidden admin action rejects requests before mutation', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);
    $parameters = [
        'user' => User::factory()->create()->id, 'category' => Category::factory()->create()->id,
        'product' => Product::factory()->create()->id, 'variant' => ProductVariant::factory()->create()->id,
        'stockItem' => StockItem::factory()->create()->id, 'option' => Option::factory()->create()->id,
        'optionGroup' => OptionGroup::factory()->create()->id, 'supplier' => Supplier::factory()->create()->id,
        'purchase' => Purchase::factory()->create()->id, 'table' => CafeTable::factory()->create()->id,
        'order' => Order::factory()->create()->id,
    ];
    $this->actingAs($user);
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/')) {
            continue;
        }
        $gates = array_filter($route->gatherMiddleware(), fn (string $middleware): bool => str_starts_with($middleware, 'can:'));
        expect($gates)->not->toBeEmpty();
        $forbidden = collect($gates)->contains(fn (string $gate): bool => $user->cannot(substr($gate, 4)));
        if (! $forbidden) {
            continue;
        }
        $url = '/'.preg_replace_callback('/\{([^}:]+)(?::[^}]+)?\}/', fn (array $match): string => (string) $parameters[$match[1]], $route->uri());
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->json($method, $url, [])->assertForbidden();
        }
    }
    $this->assertDatabaseCount('stock_movements', 0);
    expect(User::findOrFail($parameters['user'])->is_active)->toBeTrue();
})->with(['cashier', 'kitchen']);

test('role determines login and already authenticated destinations', function (string $role, string $destination): void {
    $user = User::factory()->create(['role' => $role]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute($destination);
    $this->get(route('login'))->assertRedirectToRoute($destination);
})->with([['owner', 'admin.dashboard'], ['cashier', 'admin.pos'], ['kitchen', 'admin.kitchen']]);

test('login discards forbidden missing and external intended destinations', function (string $intended): void {
    $user = User::factory()->create(['role' => 'cashier']);
    $this->withSession(['url.intended' => $intended])->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirectToRoute('admin.pos')->assertSessionMissing('url.intended');
})->with(['/admin/staff', '/admin/unknown', 'https://example.com/admin/pos']);

test('cashier can return to an authorized intended orders page', function (): void {
    $user = User::factory()->create();
    $this->withSession(['url.intended' => route('admin.orders')])->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('admin.orders');
});

test('public customer menu remains available without a staff session', function (): void {
    $table = CafeTable::factory()->create();
    $this->get(route('guest.menu', ['table' => $table->id]))->assertOk();
    $this->get(route('guest.menu.cart', ['table' => $table->id]))->assertOk();
});

test('POS product images remain accessible to owner and cashier only', function (string $role, int $status): void {
    Storage::fake('public');
    Storage::disk('public')->put('menu/role-image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $product = Product::factory()->create(['image_path' => 'menu/role-image.png']);
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.products.image', $product))->assertStatus($status);
})->with([['owner', 200], ['cashier', 200], ['kitchen', 403]]);
