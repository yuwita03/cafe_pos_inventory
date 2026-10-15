<?php

use App\Models\User;
use Database\Seeders\DevelopmentOwnerSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('auth.development_owner', [
        'email' => 'owner@example.com',
        'password' => 'local-owner-password',
    ]);
});

test('seeds one active owner with a hashed password and preserves it on reruns', function () {
    $this->seed(DevelopmentOwnerSeeder::class);
    $owner = User::query()->where('email', 'owner@example.com')->sole();
    expect($owner->role)->toBe('owner');
    expect($owner->is_active)->toBeTrue();
    expect(Hash::check('local-owner-password', $owner->password))->toBeTrue();
    $originalPassword = $owner->password;
    $owner->forceFill(['is_active' => false])->save();

    config()->set('auth.development_owner.password', 'different-password');
    $this->seed(DevelopmentOwnerSeeder::class);

    $this->assertDatabaseCount('users', 1);
    expect($owner->fresh()->password)->toBe($originalPassword);
    expect($owner->fresh()->is_active)->toBeFalse();
});

test('refuses missing or invalid owner credentials without creating a user', function (string $email, string $password, string $message) {
    config()->set('auth.development_owner', compact('email', 'password'));

    expect(fn () => app(DevelopmentOwnerSeeder::class)->run())->toThrow(RuntimeException::class, $message);

    $this->assertDatabaseCount('users', 0);
})->with([
    'missing email' => ['', 'local-owner-password', 'DEV_OWNER_EMAIL is missing.'],
    'invalid email' => ['invalid', 'local-owner-password', 'DEV_OWNER_EMAIL must be a valid email address.'],
    'missing password' => ['owner@example.com', '', 'DEV_OWNER_PASSWORD is missing.'],
    'short password' => ['owner@example.com', 'short', 'DEV_OWNER_PASSWORD must contain at least 8 characters.'],
]);

test('never seeds outside local or testing environments', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    expect(fn () => app(DevelopmentOwnerSeeder::class)->run())->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('users', 0);
})->with(['production', 'staging']);

test('does not promote or overwrite an existing account using the owner email', function () {
    $existing = User::factory()->create(['email' => 'owner@example.com', 'is_active' => false]);
    $existing->refresh();
    $attributes = $existing->getAttributes();

    expect(fn () => $this->seed(DevelopmentOwnerSeeder::class))->toThrow(RuntimeException::class);

    expect($existing->fresh()->getAttributes())->toBe($attributes);
    $this->assertDatabaseCount('users', 1);
});
