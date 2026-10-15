<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeItem> */
class RecipeItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_variant_id' => ProductVariant::factory(), 'stock_item_id' => StockItem::factory(), 'quantity' => '18.000'];
    }
}
