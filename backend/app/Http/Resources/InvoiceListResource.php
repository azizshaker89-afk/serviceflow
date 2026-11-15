<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone]),
            'service_request' => $this->whenLoaded('serviceRequest', fn () => ['id' => $this->serviceRequest->id, 'code' => $this->serviceRequest->code]),
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balance_due,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->acceptsPayments() && $this->due_date?->isPast() && ! $this->due_date->isToday(),
            'issued_at' => $this->issued_at->toIso8601String(),
        ];
    }
}
