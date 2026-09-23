<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'name' => fake()->words(2, true),
            'price_cents' => fake()->numberBetween(100, 5000),
            'active' => true,
        ];
    }

    /**
     * A product whose price was not resolved upstream.
     */
    public function unpriced(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_cents' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
