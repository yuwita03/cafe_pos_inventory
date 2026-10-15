<?php

namespace Database\Factories;

use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OptionGroup> */
class OptionGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'selection_type' => 'single', 'is_active' => true];
    }
}
