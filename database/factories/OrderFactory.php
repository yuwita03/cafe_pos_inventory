<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['created_by_user_id' => User::factory(), 'customer_name' => fake()->name(),
            'source' => 'cashier', 'fulfillment_type' => 'takeaway', 'status' => 'queued',
            'public_token' => Str::random(64), 'checkout_key' => (string) Str::uuid(),
            'checkout_fingerprint' => hash('sha256', Str::random()), 'total_idr' => 25000];
    }
}
