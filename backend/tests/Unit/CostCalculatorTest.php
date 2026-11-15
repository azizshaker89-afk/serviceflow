<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Services\Billing\CostCalculator;
use PHPUnit\Framework\TestCase;

class CostCalculatorTest extends TestCase
{
    private CostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CostCalculator;
    }

    /**
     * @return list<array{spare_part_id: int, name: string, quantity: int, unit_price: string, unit_cost: string}>
     */
    private function parts(): array
    {
        return [
            ['spare_part_id' => 1, 'name' => 'فريون R410', 'quantity' => 2, 'unit_price' => '260.00', 'unit_cost' => '180.00'],
            ['spare_part_id' => 2, 'name' => 'مكثف 35', 'quantity' => 1, 'unit_price' => '150.00', 'unit_cost' => '95.00'],
        ];
    }

    public function test_the_full_invoice_example(): void
    {
        $result = $this->calculator->calculate(
            $this->parts(),
            [['technician' => 'محمود', 'hours' => '2.50', 'hourly_rate' => '120.00']],
            null,
            '0',
            '14',
        );

        $this->assertSame('670.00', $result->partsTotal);
        $this->assertSame('455.00', $result->partsCost);
        $this->assertSame('300.00', $result->laborTotal);
        $this->assertSame('970.00', $result->subtotal);
        $this->assertSame('135.80', $result->taxAmount);
        $this->assertSame('1105.80', $result->total);
        $this->assertCount(3, $result->lines);
    }

    public function test_discount_is_applied_before_tax(): void
    {
        $result = $this->calculator->calculate($this->parts(), [['technician' => 'محمود', 'hours' => '2.50', 'hourly_rate' => '120.00']], null, '70', '14');

        $this->assertSame('126.00', $result->taxAmount);
        $this->assertSame('1026.00', $result->total);
    }

    public function test_a_fixed_category_fee_replaces_hourly_labor(): void
    {
        $result = $this->calculator->calculate([], [['technician' => 'محمود', 'hours' => '5.00', 'hourly_rate' => '120.00']], '450.00', '0', '14', 'تركيب تكييف');

        $this->assertSame('450.00', $result->laborTotal);
        $this->assertSame('مصنعية ثابتة تركيب تكييف', $result->lines[0]['description']);
        $this->assertSame('513.00', $result->total);
    }

    public function test_hours_at_different_rates_become_separate_lines(): void
    {
        $result = $this->calculator->calculate([], [
            ['technician' => 'محمود', 'hours' => '1.00', 'hourly_rate' => '100.00'],
            ['technician' => 'محمود', 'hours' => '0.50', 'hourly_rate' => '100.00'],
            ['technician' => 'محمود', 'hours' => '1.00', 'hourly_rate' => '120.00'],
        ], null, '0', '0');

        $this->assertCount(2, $result->lines);
        $this->assertSame('1.50', $result->lines[0]['quantity']);
        $this->assertSame('150.00', $result->lines[0]['total']);
        $this->assertSame('270.00', $result->total);
    }

    public function test_tax_is_rounded_half_up_to_the_piastre(): void
    {
        $result = $this->calculator->calculate([], [['technician' => 'محمود', 'hours' => '0.25', 'hourly_rate' => '0.18']], null, '0', '14');

        $this->assertSame('0.05', $result->subtotal);
        $this->assertSame('0.01', $result->taxAmount);
        $this->assertSame('0.06', $result->total);
    }

    public function test_floating_point_drift_does_not_happen(): void
    {
        $parts = array_fill(0, 10, ['spare_part_id' => 1, 'name' => 'x', 'quantity' => 1, 'unit_price' => '0.10', 'unit_cost' => '0.05']);

        $result = $this->calculator->calculate($parts, [], null, '0', '0');

        $this->assertSame('1.00', $result->total);
    }

    public function test_a_discount_larger_than_the_subtotal_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->calculator->calculate($this->parts(), [], null, '700', '14');
    }

    public function test_a_negative_discount_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->calculator->calculate($this->parts(), [], null, '-5', '14');
    }

    public function test_labor_only_invoice_without_parts(): void
    {
        $result = $this->calculator->calculate([], [['technician' => 'كريم', 'hours' => '1.50', 'hourly_rate' => '100.00']], null, '0', '14');

        $this->assertSame('0.00', $result->partsTotal);
        $this->assertSame('171.00', $result->total);
    }
}
