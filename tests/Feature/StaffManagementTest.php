<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
});

/** @return array{name: string, email: string, role: string, is_active: bool, password: string, password_confirmation: string} */
function staffPayload(array $overrides = []): array
{
    return [...['name' => 'New Staff', 'email' => 'staff@example.com', 'role' => 'cashier', 'is_active' => true,
        'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'], ...$overrides];
}

test('staff list filters paginates and never exposes credentials', function (): void {
    User::factory()->count(12)->create(['role' => 'kitchen', 'is_active' => false]);
    $match = User::factory()->create(['name' => 'Dina Kitchen', 'email' => 'dina@example.com', 'role' => 'kitchen', 'is_active' => false]);
    $this->get(route('admin.staff', ['role' => 'kitchen', 'status' => 'inactive', 'per_page' => 10]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/staff/Index')->has('staff', 10)
        ->where('pagination.total', 13)->where('pagination.last_page', 2)->missing('staff.0.password')->missing('staff.0.remember_token'));
    foreach (['DINA', 'dina@example.com'] as $query) {
        $this->get(route('admin.staff', ['q' => $query, 'role' => 'kitchen', 'status' => 'inactive']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('staff', 1)->where('staff.0.id', $match->id));
    }
    $this->get(route('admin.staff', ['q' => 'no-such-person']))->assertInertia(fn (Assert $page) => $page->has('staff', 0));
});

test('owner creates staff with normalized details and a hashed password', function (): void {
    $this->post(route('admin.staff.store'), staffPayload(['name' => '  Dina  ', 'email' => ' DINA@EXAMPLE.COM ']))
        ->assertRedirectToRoute('admin.staff')->assertSessionHasNoErrors();
    $staff = User::where('email', 'dina@example.com')->firstOrFail();
    expect($staff->name)->toBe('Dina')->and($staff->role)->toBe('cashier')->and($staff->is_active)->toBeTrue()
        ->and(Hash::check('new-password-123', $staff->password))->toBeTrue();
});

test('owner updates identity role and status while a blank password preserves its hash', function (): void {
    $staff = User::factory()->create();
    $hash = $staff->password;
    $this->from(route('admin.staff'))->put(route('admin.staff.update', $staff), staffPayload(['role' => 'kitchen', 'password' => '', 'password_confirmation' => '']))
        ->assertRedirectToRoute('admin.staff')->assertSessionHasNoErrors();
    expect($staff->refresh()->name)->toBe('New Staff')->and($staff->email)->toBe('staff@example.com')
        ->and($staff->role)->toBe('kitchen')->and($staff->password)->toBe($hash)->and($staff->email_verified_at)->toBeNull();
});

test('owner can save an unchanged email', function (): void {
    $staff = User::factory()->create();
    $this->put(route('admin.staff.update', $staff), staffPayload(['email' => $staff->email]))->assertSessionHasNoErrors();
});

test('staff input is validated without creating accounts', function (array $overrides, string $field): void {
    $this->post(route('admin.staff.store'), staffPayload($overrides))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('users', 1);
})->with([
    'blank name' => [['name' => '   '], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'invalid role' => [['role' => 'manager'], 'role'],
    'invalid status' => [['is_active' => 'maybe'], 'is_active'],
    'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'different'], 'password'],
]);

test('email uniqueness applies to creation and updates after normalization', function (): void {
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $target = User::factory()->create();
    $this->post(route('admin.staff.store'), staffPayload(['email' => 'EXISTING@EXAMPLE.COM']))->assertSessionHasErrors('email');
    $this->put(route('admin.staff.update', $target), staffPayload(['email' => $existing->email]))->assertSessionHasErrors('email');
    expect($target->refresh()->email)->not->toBe($existing->email);
});

test('deactivation and reactivation preserve staff and historical orders', function (): void {
    $staff = User::factory()->create();
    $order = Order::factory()->create(['created_by_user_id' => $staff->id]);
    $owner = auth()->user();
    $this->put(route('admin.staff.update', $staff), staffPayload(['is_active' => false]))->assertSessionHasNoErrors();
    expect($staff->refresh()->is_active)->toBeFalse();
    $this->actingAs($staff)->postJson(route('admin.pos.checkout'), [])->assertUnauthorized();
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'new-password-123'])->assertSessionHasErrors('email');
    $this->actingAs($owner)->put(route('admin.staff.update', $staff), staffPayload(['password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
    expect($staff->refresh()->is_active)->toBeTrue();
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'created_by_user_id' => $staff->id]);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'new-password-123'])->assertRedirectToRoute('admin.pos');
});

test('owner cannot demote or deactivate their own account', function (array $overrides): void {
    $owner = auth()->user();
    User::factory()->create(['role' => 'owner']);
    $this->put(route('admin.staff.update', $owner), staffPayload(['email' => $owner->email, ...$overrides]))->assertSessionHasErrors('role');
    expect($owner->refresh()->role)->toBe('owner')->and($owner->is_active)->toBeTrue();
})->with([[['role' => 'cashier']], [['role' => 'owner', 'is_active' => false]]]);

test('another owner can demote an owner while preserving an active owner', function (): void {
    $other = User::factory()->create(['role' => 'owner']);
    $this->put(route('admin.staff.update', $other), staffPayload())->assertSessionHasNoErrors();
    expect($other->refresh()->role)->toBe('cashier')->and(User::where('role', 'owner')->where('is_active', true)->count())->toBe(1);
});

test('staff updates recheck the actor after locking instead of trusting stale authorization', function (): void {
    $actor = auth()->user();
    $target = User::factory()->create();
    User::whereKey($actor->id)->update(['role' => 'cashier']);
    $this->putJson(route('admin.staff.update', $target), staffPayload())->assertForbidden();
    expect($target->refresh()->name)->not->toBe('New Staff');
});

test('changing a role takes effect on the next protected request', function (): void {
    $staff = User::factory()->create();
    $this->put(route('admin.staff.update', $staff), staffPayload(['role' => 'kitchen', 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
    $this->actingAs($staff->fresh())->get(route('admin.pos'))->assertForbidden();
    $this->get(route('admin.kitchen'))->assertOk();
});

test('password reset rotates remembered login and rejects existing password sessions', function (): void {
    $staff = User::factory()->create();
    $oldHash = $staff->password;
    $oldToken = $staff->remember_token;
    $this->put(route('admin.staff.update', $staff), staffPayload())->assertSessionHasNoErrors();
    expect($staff->refresh()->remember_token)->not->toBe($oldToken)->and(Hash::check('new-password-123', $staff->password))->toBeTrue();
    $this->actingAs($staff)->withSession(['password_hash_web' => $oldHash])->get(route('admin.pos'))->assertRedirectToRoute('login');
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'new-password-123'])->assertRedirectToRoute('admin.pos');
});

test('reset removes database sessions belonging only to the target staff', function (): void {
    $staff = User::factory()->create();
    $owner = auth()->user();
    foreach (['target-session' => $staff->id, 'owner-session' => $owner->id] as $id => $userId) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
    }
    config(['session.driver' => 'database']);
    $this->put(route('admin.staff.update', $staff), staffPayload())->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'owner-session']);
});

test('self password reset preserves the current database session and removes other sessions', function (): void {
    $owner = auth()->user();
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();
    app('auth')->forgetGuards();
    $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'password'])->assertRedirectToRoute('admin.dashboard');
    $sessionId = session()->getId();
    DB::table('sessions')->insert(['id' => 'other-owner-device', 'user_id' => $owner->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
    $this->withCookie(config('session.cookie'), $sessionId)->from(route('admin.staff'))
        ->put(route('admin.staff.update', $owner), staffPayload(['email' => $owner->email, 'role' => 'owner']))
        ->assertRedirectToRoute('admin.staff')->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $owner->id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-owner-device']);
    app('auth')->forgetGuards();
    $this->withCookie(config('session.cookie'), $sessionId)->get(route('admin.staff'))->assertOk()->assertSessionHas('status', 'Perubahan akun staf berhasil disimpan.');
    $this->assertAuthenticatedAs($owner);
});
