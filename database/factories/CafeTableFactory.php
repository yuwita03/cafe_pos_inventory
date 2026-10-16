<?php

namespace Database\Factories;

use App\Models\CafeTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CafeTable> */
class CafeTableFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['label' => fake()->unique()->bothify('T-####'), 'qr_token' => Str::random(64), 'is_active' => true];
    }
}
