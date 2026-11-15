<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an invoice nobody has paid against (a wrong discount, for example).
 * The cancelled invoice stays as history and a corrected one can then be issued.
 */
class CancelInvoice
{
    public function handle(Invoice $invoice, User $accountant, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $accountant, $reason): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->isCancellable()) {
                throw new BusinessRuleException('لا يمكن إلغاء فاتورة عليها دفعات أو ملغاة بالفعل.');
            }

            $locked->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $accountant->id,
                'cancellation_reason' => $reason,
            ])->save();

            activity('billing')
                ->performedOn($locked)
                ->causedBy($accountant)
                ->event('invoice_cancelled')
                ->withProperties(['reason' => $reason])
                ->log("{$locked->invoice_number}: إلغاء الفاتورة");

            return $locked;
        });
    }
}
