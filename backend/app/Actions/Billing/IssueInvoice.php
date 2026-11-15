<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\CostBreakdown;
use App\Services\Billing\CostCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Turns a completed request into an invoice.
 *
 * The figures come from data already frozen on the request (part prices snapshotted when issued,
 * hourly rates snapshotted when logged), so issuing today or next week gives the same invoice.
 * Every line is stored in invoice_items, so the total can always be explained.
 */
class IssueInvoice
{
    public function __construct(private CostCalculator $calculator) {}

    /**
     * The exact numbers the invoice would have, without saving anything.
     */
    public function preview(ServiceRequest $serviceRequest, string $discount = '0'): CostBreakdown
    {
        $serviceRequest->loadMissing(['parts.sparePart', 'workLogs.technician', 'category']);

        return $this->calculator->calculate(
            $serviceRequest->parts->map(fn ($line) => [
                'spare_part_id' => $line->spare_part_id,
                'name' => $line->sparePart->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'unit_cost' => $line->unit_cost,
            ])->all(),
            $serviceRequest->workLogs->map(fn ($log) => [
                'technician' => $log->technician->name,
                'hours' => $log->hours,
                'hourly_rate' => $log->hourly_rate,
            ])->all(),
            $serviceRequest->category->default_fee,
            $discount,
            Setting::taxRate(),
            $serviceRequest->category->name,
        );
    }

    public function handle(ServiceRequest $serviceRequest, User $accountant, string $discount = '0', ?string $dueDate = null, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($serviceRequest, $accountant, $discount, $dueDate, $notes): Invoice {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if ($locked->status !== ServiceRequestStatus::Completed) {
                throw new BusinessRuleException('لا يمكن إصدار فاتورة قبل إتمام الصيانة.');
            }

            if ($locked->invoices()->where('status', '!=', InvoiceStatus::Cancelled->value)->exists()) {
                throw new BusinessRuleException("يوجد بالفعل فاتورة سارية للطلب {$locked->code}.");
            }

            $breakdown = $this->preview($locked, $discount);

            $invoice = $locked->invoices()->create([
                'customer_id' => $locked->customer_id,
                'issued_by' => $accountant->id,
                'parts_total' => $breakdown->partsTotal,
                'parts_cost' => $breakdown->partsCost,
                'labor_total' => $breakdown->laborTotal,
                'subtotal' => $breakdown->subtotal,
                'discount' => $breakdown->discount,
                'tax_rate' => $breakdown->taxRate,
                'tax_amount' => $breakdown->taxAmount,
                'total' => $breakdown->total,
                'due_date' => $dueDate ?? now()->addDays(7)->toDateString(),
                'issued_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($breakdown->lines as $line) {
                $invoice->items()->create([
                    'type' => InvoiceItemType::from($line['type']),
                    'spare_part_id' => $line['spare_part_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['total'],
                ]);
            }

            $invoice->refresh();

            activity('billing')
                ->performedOn($invoice)
                ->causedBy($accountant)
                ->event('invoice_issued')
                ->withProperties(['service_request' => $locked->code, 'total' => $invoice->total])
                ->log("{$invoice->invoice_number}: إصدار فاتورة بقيمة {$invoice->total} للطلب {$locked->code}");

            return $invoice;
        });
    }
}
