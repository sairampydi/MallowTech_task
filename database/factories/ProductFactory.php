<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('SKU-####??'),
            'price' => fake()->randomFloat(2, 1, 1000),
            'tax_rate' => fake()->randomElement([0, 5, 10, 15, 20]),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
