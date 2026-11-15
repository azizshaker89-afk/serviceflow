<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw invoice rows for tests that only need one to exist (listing, filtering).
 * Tests about invoice maths go through the IssueInvoice action instead.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'customer_id' => Customer::factory(),
            'issued_by' => User::factory(),
            'parts_total' => '300.00',
            'parts_cost' => '200.00',
            'labor_total' => '200.00',
            'subtotal' => '500.00',
            'discount' => '0.00',
            'tax_rate' => '14.00',
            'tax_amount' => '70.00',
            'total' => '570.00',
            'due_date' => now()->addWeek()->toDateString(),
            'issued_at' => now(),
        ];
    }
}
