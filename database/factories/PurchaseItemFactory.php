<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'stock_item_id' => StockItem::factory(),
            'quantity' => '30.000',
            'line_cost_idr' => 300000,
        ];
    }
}
