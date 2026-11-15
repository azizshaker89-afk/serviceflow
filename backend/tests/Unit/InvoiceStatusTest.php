<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function test_status_is_derived_from_paid_amount(): void
    {
        $this->assertSame(InvoiceStatus::Unpaid, InvoiceStatus::fromAmounts('1140.00', '0.00'));
        $this->assertSame(InvoiceStatus::PartiallyPaid, InvoiceStatus::fromAmounts('1140.00', '500.00'));
        $this->assertSame(InvoiceStatus::Paid, InvoiceStatus::fromAmounts('1140.00', '1140.00'));
    }

    public function test_a_single_piastre_short_is_still_partially_paid(): void
    {
        $this->assertSame(InvoiceStatus::PartiallyPaid, InvoiceStatus::fromAmounts('1140.00', '1139.99'));
    }
}
