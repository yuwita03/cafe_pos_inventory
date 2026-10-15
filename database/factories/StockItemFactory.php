<?php

namespace Database\Factories;

use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockItem> */
class StockItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(3, true), 'base_unit' => 'g', 'on_hand_qty' => '0.000', 'reorder_point' => '0.000', 'is_active' => true];
    }
}
