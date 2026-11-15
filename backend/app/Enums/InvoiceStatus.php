<?php

namespace App\Enums;

/**
 * Unpaid / PartiallyPaid / Paid are derived from the sum of payments, never set by hand.
 * Only Cancelled is an explicit action.
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مدفوعة',
            self::PartiallyPaid => 'مدفوعة جزئيًا',
            self::Paid => 'مدفوعة',
            self::Cancelled => 'ملغاة',
        };
    }

    public static function fromAmounts(string $total, string $paid): self
    {
        if (bccomp($paid, '0', 2) <= 0) {
            return self::Unpaid;
        }

        return bccomp($paid, $total, 2) >= 0 ? self::Paid : self::PartiallyPaid;
    }
}
