<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Payments are never edited; the invoice's paid amount and status are recalculated from
 * the payments table on every payment, inside the same locked transaction, so the cached
 * amount_paid can never disagree with the payments that justify it.
 */
class RecordPayment
{
    public function handle(Invoice $invoice, string $amount, PaymentMethod $method, User $accountant, ?string $reference = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $accountant, $reference, $notes): Payment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->acceptsPayments()) {
                throw new BusinessRuleException($locked->status === InvoiceStatus::Paid
                    ? 'الفاتورة مدفوعة بالكامل ولا تقبل دفعات أخرى.'
                    : 'لا يمكن تسجيل دفعة على فاتورة ملغاة.');
            }

            if (bccomp($amount, '0', 2) <= 0) {
                throw new BusinessRuleException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
            }

            $balance = bcsub($locked->total, $locked->amount_paid, 2);

            if (bccomp($amount, $balance, 2) > 0) {
                throw new BusinessRuleException("المبلغ أكبر من المتبقي على الفاتورة ({$balance} ج.م).");
            }

            $payment = $locked->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => now(),
                'received_by' => $accountant->id,
                'notes' => $notes,
            ]);

            $paid = $locked->payments()->pluck('amount')->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            $locked->forceFill([
                'amount_paid' => $paid,
                'status' => InvoiceStatus::fromAmounts($locked->total, $paid),
            ])->save();

            activity('billing')
                ->performedOn($locked)
                ->causedBy($accountant)
                ->event('payment_recorded')
                ->withProperties(['amount' => $amount, 'method' => $method->value, 'status' => $locked->status->value])
                ->log("{$locked->invoice_number}: دفعة {$amount} ({$method->label()})");

            $invoice->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });
    }
}
