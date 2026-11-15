<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => fake()->unique()->numerify('01'.fake()->randomElement(['0', '1', '2', '5']).'########'),
            'email' => fake()->optional(0.4)->safeEmail(),
            'city' => fake()->randomElement(['القاهرة', 'الجيزة', 'الإسكندرية', 'المنصورة', 'طنطا', 'الزقازيق']),
            'address' => fake()->streetAddress(),
            'notes' => null,
        ];
    }
}
