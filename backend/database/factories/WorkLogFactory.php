<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLog>
 */
class WorkLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'technician_id' => User::factory(),
            'hours' => fake()->randomElement(['0.50', '1.00', '1.50', '2.00', '3.00']),
            'hourly_rate' => '100.00',
            'note' => null,
            'logged_at' => now(),
        ];
    }
}
