<?php

namespace Database\Seeders;

use App\Models\StockItem;
use Illuminate\Database\Seeder;

class StockItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Coffee beans', 'g'], ['Fresh milk', 'ml'], ['Simple syrup', 'ml'], ['Croissant', 'pcs']] as [$name, $unit]) {
            StockItem::firstOrCreate(['name' => $name], ['base_unit' => $unit, 'on_hand_qty' => '0.000', 'reorder_point' => '0.000', 'is_active' => true]);
        }
    }
}
