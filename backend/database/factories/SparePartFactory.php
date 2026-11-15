<?php

namespace Database\Factories;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: stock_quantity is not fillable. Use the withStock() state in tests;
 * real stock changes go through the inventory actions.
 *
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(20, 2000);

        return [
            'sku' => fake()->unique()->bothify('SP-####'),
            'name' => fake()->words(3, true),
            'description' => null,
            'unit_cost' => number_format($cost, 2, '.', ''),
            'unit_price' => number_format($cost * 1.3, 2, '.', ''),
            'min_stock' => 5,
            'is_active' => true,
        ];
    }

    public function withStock(int $quantity): static
    {
        return $this->afterMaking(fn (SparePart $part) => $part->stock_quantity = $quantity);
    }
}
