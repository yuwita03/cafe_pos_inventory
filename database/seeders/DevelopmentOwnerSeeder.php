<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Development owner seeding is only allowed in local and testing environments.');
        }

        $credentials = config('auth.development_owner');
        $validator = Validator::make(is_array($credentials) ? $credentials : [], [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.required' => 'DEV_OWNER_EMAIL is missing.',
            'email.string' => 'DEV_OWNER_EMAIL must be a string.',
            'email.email' => 'DEV_OWNER_EMAIL must be a valid email address.',
            'email.max' => 'DEV_OWNER_EMAIL must not exceed 255 characters.',
            'password.required' => 'DEV_OWNER_PASSWORD is missing.',
            'password.string' => 'DEV_OWNER_PASSWORD must be a string.',
            'password.min' => 'DEV_OWNER_PASSWORD must contain at least 8 characters.',
        ]);
        if ($validator->fails()) {
            throw new RuntimeException(implode(' ', $validator->errors()->all()));
        }

        /** @var array{email: string, password: string} $credentials */
        $credentials = $validator->validated();
        $owner = User::query()->firstOrCreate([
            'email' => Str::lower($credentials['email']),
        ], [
            'name' => 'Owner',
            'password' => $credentials['password'],
            'role' => 'owner',
            'is_active' => true,
        ]);

        if ($owner->role !== 'owner') {
            throw new RuntimeException('An existing non-owner account uses DEV_OWNER_EMAIL. No account was changed.');
        }

        $this->command->info($owner->wasRecentlyCreated
            ? 'Development owner created successfully.'
            : 'Owner already exists; credentials and account status were preserved.');
    }
}
