<?php

namespace App\Actions\ServiceRequests;

use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

/**
 * A customer submits a request from the public site without an account.
 *
 * The phone number identifies the customer. An existing customer is reused but never
 * overwritten: anyone can type any phone number, so public input may only fill blanks.
 * Priority is always Normal; reception re-prioritises after reviewing the request.
 */
class SubmitOnlineServiceRequest
{
    public function __construct(private CreateServiceRequest $createServiceRequest) {}

    /**
     * @param  array{name: string, phone: string, email?: ?string, city?: ?string, address: string, service_category_id: int, description: string, preferred_date?: ?string}  $input
     */
    public function handle(array $input): ServiceRequest
    {
        return DB::transaction(function () use ($input): ServiceRequest {
            $customer = Customer::query()->createOrFirst(
                ['phone' => $input['phone']],
                [
                    'name' => $input['name'],
                    'email' => $input['email'] ?? null,
                    'city' => $input['city'] ?? null,
                    'address' => $input['address'],
                ],
            );

            if (! $customer->wasRecentlyCreated) {
                $customer->fill(array_filter([
                    'email' => $customer->email ? null : ($input['email'] ?? null),
                    'city' => $customer->city ? null : ($input['city'] ?? null),
                    'address' => $customer->address ? null : $input['address'],
                ]))->save();
            }

            return $this->createServiceRequest->handle($customer, [
                'service_category_id' => $input['service_category_id'],
                'description' => $input['description'],
                'priority' => Priority::Normal,
                'address' => $input['address'],
                'preferred_date' => $input['preferred_date'] ?? null,
            ], null, RequestSource::Online);
        });
    }
}
