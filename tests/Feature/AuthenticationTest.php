<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can view login and the public home', function () {
    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    $this->get(route('home'))->assertOk();
});

test('an active owner can log in and receives a new session', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->withSession(['_token' => 'initial-token']);
    $previousSessionId = session()->getId();

    $this->post(route('login.store'), ['email' => strtoupper($owner->email), 'password' => 'password'])
        ->assertRedirectToRoute('admin.dashboard');

    $this->assertAuthenticatedAs($owner);
    expect(session()->getId())->not->toBe($previousSessionId);
    $this->get(route('login'))->assertRedirectToRoute('admin.dashboard');
    $this->get(route('admin.dashboard'))->assertOk();
});

test('login returns to the originally requested management page', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $this->get(route('admin.inventory.index'))->assertRedirectToRoute('login');

    $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirectToRoute('admin.inventory.index');
});

test('incorrect credentials and inactive accounts receive the same generic error', function (string $email, string $password, bool $active) {
    User::factory()->create(['email' => 'staff@example.com', 'is_active' => $active]);

    $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => $password])
        ->assertRedirectToRoute('login')->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.'])
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
})->with([
    'wrong password' => ['staff@example.com', 'incorrect', true],
    'inactive account' => ['staff@example.com', 'password', false],
    'unknown email' => ['unknown@example.com', 'password', true],
]);

test('login validates required fields', function () {
    $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
    $this->assertGuest();
});

test('five failed attempts block login temporarily and the limit expires', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => strtoupper($owner->email), 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam satu menit.']);
    $this->assertGuest();

    $this->travel(61)->seconds();
    $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirectToRoute('admin.dashboard');
    $this->assertAuthenticatedAs($owner);
});

test('logout clears the session and prevents further management access', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->withSession(['private_data' => 'remove-me', '_token' => 'old-token']);

    $this->post(route('logout'))->assertRedirectToRoute('login')->assertSessionMissing('private_data');

    $this->assertGuest();
    expect(session()->token())->not->toBe('old-token');
    $this->get(route('admin.inventory.index'))->assertRedirectToRoute('login');
    $this->get('/logout')->assertMethodNotAllowed();
});

test('deactivating an account ends its existing staff session before a write', function () {
    $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
    $this->actingAs($owner);
    $owner->forceFill(['is_active' => false])->save();

    $this->postJson(route('admin.inventory.items.store'), [])
        ->assertUnauthorized();

    $this->assertGuest();
    $this->assertDatabaseCount('stock_items', 0);
});

test('all management reads and writes reject guests without changing data', function () {
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/')) {
            continue;
        }
        $url = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->call($method, $url)->assertRedirectToRoute('login');
            $this->json($method, $url)->assertUnauthorized();
        }
    }

    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('stock_items', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});
