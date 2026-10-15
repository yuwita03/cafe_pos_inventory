<?php

namespace Database\Factories;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['stock_item_id' => StockItem::factory()->state(['on_hand_qty' => '1.000']), 'quantity_delta' => '1.000', 'reason' => 'Opening balance', 'source_key' => (string) Str::uuid(), 'actor_user_id' => null];
    }
}
