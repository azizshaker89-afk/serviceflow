<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'notes' => $this->notes,
            'service_requests_count' => $this->whenCounted('serviceRequests'),
            'billed_total' => $this->when(
                array_key_exists('billed_total', $this->getAttributes()) && (bool) $request->user()?->can('invoices.view'),
                fn () => bcadd((string) ($this->billed_total ?? 0), '0', 2),
            ),
            'last_request_at' => $this->whenHas('last_request_at', fn () => $this->last_request_at),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
