<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'default_fee' => null,
            'is_active' => true,
        ];
    }

    public function withFixedFee(string $fee = '250.00'): static
    {
        return $this->state(fn (array $attributes) => ['default_fee' => $fee]);
    }
}
