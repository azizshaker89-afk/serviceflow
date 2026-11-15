<?php

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer may see about their own request: progress only.
 * No internal notes, staff names, costs of parts or other customers' data.
 *
 * @mixin ServiceRequest
 */
class PublicServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;

        return [
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'category' => $this->category->name,
            'technician_assigned' => $this->assigned_technician_id !== null,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'timeline' => $this->statusHistories->sortBy('id')->values()->map(fn ($history) => [
                'status' => $history->to_status->value,
                'status_label' => $history->to_status->label(),
                'at' => $history->created_at->toIso8601String(),
            ]),
            'invoice' => $invoice && $invoice->status !== InvoiceStatus::Cancelled ? [
                'number' => $invoice->invoice_number,
                'total' => $invoice->total,
                'balance_due' => $invoice->balance_due,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ] : null,
        ];
    }
}
