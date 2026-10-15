<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'name' => 'Regular', 'price_idr' => 25000, 'is_available' => true];
    }
}
