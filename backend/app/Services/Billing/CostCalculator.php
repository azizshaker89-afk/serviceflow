<?php

namespace App\Services\Billing;

use App\Exceptions\BusinessRuleException;

/**
 * Pure maintenance-cost arithmetic: no database, no clock, no globals, so it is unit tested directly.
 *
 *   parts      = Σ quantity × unit_price          (unit_cost is summed separately for margin reports)
 *   labor      = fixed category fee, or Σ hours × the rate snapshotted on each work log
 *   subtotal   = parts + labor
 *   taxable    = subtotal − discount               (discount is applied BEFORE tax)
 *   tax        = taxable × tax_rate / 100, rounded half-up to the piastre
 *   total      = taxable + tax
 *
 * Money is handled as decimal strings with bcmath; floats would drift by fractions of a piastre.
 */
class CostCalculator
{
    private const SCALE = 6;

    /**
     * @param  list<array{spare_part_id: int, name: string, quantity: int, unit_price: string, unit_cost: string}>  $parts
     * @param  list<array{technician: string, hours: string, hourly_rate: string}>  $workLogs
     */
    public function calculate(
        array $parts,
        array $workLogs,
        ?string $fixedLaborFee,
        string $discount,
        string $taxRate,
        string $serviceName = '',
    ): CostBreakdown {
        $lines = [];
        $partsTotal = '0';
        $partsCost = '0';

        foreach ($parts as $part) {
            $lineTotal = bcmul((string) $part['quantity'], $part['unit_price'], self::SCALE);
            $partsTotal = bcadd($partsTotal, $lineTotal, self::SCALE);
            $partsCost = bcadd($partsCost, bcmul((string) $part['quantity'], $part['unit_cost'], self::SCALE), self::SCALE);

            $lines[] = [
                'type' => 'part',
                'description' => $part['name'],
                'spare_part_id' => $part['spare_part_id'],
                'quantity' => $this->money((string) $part['quantity']),
                'unit_price' => $this->money($part['unit_price']),
                'total' => $this->money($lineTotal),
            ];
        }

        [$laborLines, $laborTotal] = $fixedLaborFee !== null
            ? $this->fixedLabor($fixedLaborFee, $serviceName)
            : $this->hourlyLabor($workLogs);

        $lines = [...$lines, ...$laborLines];
        $subtotal = bcadd($partsTotal, $laborTotal, self::SCALE);

        if (bccomp($discount, '0', self::SCALE) < 0) {
            throw new BusinessRuleException('الخصم لا يمكن أن يكون سالبًا.');
        }

        if (bccomp($discount, $subtotal, self::SCALE) > 0) {
            throw new BusinessRuleException('الخصم أكبر من إجمالي الفاتورة قبل الضريبة ('.$this->money($subtotal).' ج.م).');
        }

        $taxable = bcsub($subtotal, $discount, self::SCALE);
        $taxAmount = $this->money(bcdiv(bcmul($taxable, $taxRate, self::SCALE), '100', self::SCALE));

        return new CostBreakdown(
            lines: $lines,
            partsTotal: $this->money($partsTotal),
            partsCost: $this->money($partsCost),
            laborTotal: $this->money($laborTotal),
            subtotal: $this->money($subtotal),
            discount: $this->money($discount),
            taxRate: $this->money($taxRate),
            taxAmount: $taxAmount,
            total: $this->money(bcadd($this->money($taxable), $taxAmount, self::SCALE)),
        );
    }

    /**
     * @return array{0: list<array{type: string, description: string, spare_part_id: null, quantity: string, unit_price: string, total: string}>, 1: string}
     */
    private function fixedLabor(string $fee, string $serviceName): array
    {
        return [[[
            'type' => 'labor',
            'description' => trim("مصنعية ثابتة {$serviceName}"),
            'spare_part_id' => null,
            'quantity' => '1.00',
            'unit_price' => $this->money($fee),
            'total' => $this->money($fee),
        ]], $fee];
    }

    /**
     * One line per technician and rate, so a rate change mid-job shows as two honest lines.
     *
     * @param  list<array{technician: string, hours: string, hourly_rate: string}>  $workLogs
     * @return array{0: list<array{type: string, description: string, spare_part_id: null, quantity: string, unit_price: string, total: string}>, 1: string}
     */
    private function hourlyLabor(array $workLogs): array
    {
        $groups = [];

        foreach ($workLogs as $log) {
            $key = $log['technician'].'|'.$log['hourly_rate'];
            $groups[$key] ??= ['technician' => $log['technician'], 'rate' => $log['hourly_rate'], 'hours' => '0'];
            $groups[$key]['hours'] = bcadd($groups[$key]['hours'], $log['hours'], self::SCALE);
        }

        $lines = [];
        $total = '0';

        foreach ($groups as $group) {
            $lineTotal = bcmul($group['hours'], $group['rate'], self::SCALE);
            $total = bcadd($total, $lineTotal, self::SCALE);
            $lines[] = [
                'type' => 'labor',
                'description' => "مصنعية: {$group['technician']}",
                'spare_part_id' => null,
                'quantity' => $this->money($group['hours']),
                'unit_price' => $this->money($group['rate']),
                'total' => $this->money($lineTotal),
            ];
        }

        return [$lines, $total];
    }

    /**
     * Round half-up to 2 decimal places (bcmath truncates, so add half a piastre first).
     */
    private function money(string $value): string
    {
        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $half, self::SCALE), '0', 2);
    }
}
