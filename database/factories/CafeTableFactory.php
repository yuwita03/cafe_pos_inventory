<?php

namespace Database\Factories;

use App\Models\CafeTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CafeTable> */
class CafeTableFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['label' => fake()->unique()->bothify('T-####'), 'is_active' => true];
    }
}
