<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\OptionIngredient;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OptionIngredient> */
class OptionIngredientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['option_id' => Option::factory(), 'stock_item_id' => StockItem::factory()->state(['base_unit' => 'ml']), 'additional_qty' => '5.000'];
    }
}
