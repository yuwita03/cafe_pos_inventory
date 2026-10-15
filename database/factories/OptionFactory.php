<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Option> */
class OptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['option_group_id' => OptionGroup::factory(), 'name' => fake()->unique()->word(), 'price_delta_idr' => 0, 'is_active' => true];
    }
}
