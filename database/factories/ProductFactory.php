<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'name' => fake()->words(2, true), 'description' => fake()->sentence(), 'is_active' => true];
    }
}
