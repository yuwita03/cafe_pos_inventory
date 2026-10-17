<?php

namespace Database\Seeders;

use App\Models\CafeTable;
use Illuminate\Database\Seeder;

class CafeTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        foreach (range(1, 8) as $number) {
            CafeTable::firstOrCreate(['label' => 'A'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)], ['is_active' => true]);
        }
    }
}
