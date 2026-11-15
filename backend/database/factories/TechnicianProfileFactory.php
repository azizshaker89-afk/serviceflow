<?php

namespace Database\Factories;

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianProfile>
 */
class TechnicianProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'specialization' => fake()->randomElement(['تكييف وتبريد', 'كهرباء', 'سباكة', 'أجهزة منزلية', 'نجارة']),
            'hourly_rate' => fake()->randomElement(['80.00', '100.00', '120.00', '150.00']),
            'is_available' => true,
        ];
    }
}
