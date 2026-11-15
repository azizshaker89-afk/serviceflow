<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * @mixin Invoice
 */
class InvoiceResource extends InvoiceListResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['customer', 'serviceRequest.category', 'serviceRequest.technician', 'issuer', 'items', 'payments.receiver'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::toArray($request),
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'service_request' => $this->whenLoaded('serviceRequest', fn () => [
                'id' => $this->serviceRequest->id,
                'code' => $this->serviceRequest->code,
                'category' => $this->serviceRequest->category?->name,
                'technician' => $this->serviceRequest->technician?->name,
                'completed_at' => $this->serviceRequest->completed_at?->toIso8601String(),
            ]),
            'issued_by' => $this->issuer?->name,
            'parts_total' => $this->parts_total,
            'labor_total' => $this->labor_total,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'parts_cost' => $this->when((bool) $user?->can('reports.financial') || (bool) $user?->can('invoices.manage'), $this->parts_cost),
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->sortBy('id')->values()->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'received_by' => $payment->receiver?->name,
                'paid_at' => $payment->paid_at->toIso8601String(),
            ])),
            'allowed_actions' => array_values(array_filter([
                $user?->can('recordPayment', $this->resource) ? 'record_payment' : null,
                $user?->can('cancel', $this->resource) ? 'cancel' : null,
            ])),
        ];
    }
}
