<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * States here set raw columns for test setup only. Application code changes
 * status exclusively through the workflow actions.
 *
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_category_id' => ServiceCategory::factory(),
            'created_by' => User::factory(),
            'priority' => fake()->randomElement(Priority::cases()),
            'description' => fake()->realText(120),
            'address' => fake()->streetAddress(),
            'preferred_date' => fake()->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }

    public function assignedTo(User $technician): static
    {
        return $this->afterMaking(function (ServiceRequest $request) use ($technician): void {
            $request->status = ServiceRequestStatus::Assigned;
            $request->assigned_technician_id = $technician->id;
            $request->assigned_at = now();
        });
    }

    public function inStatus(ServiceRequestStatus $status): static
    {
        return $this->afterMaking(fn (ServiceRequest $request) => $request->status = $status);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => ['due_at' => now()->subHour()]);
    }
}
