<?php

namespace App\Services\Billing;

/**
 * The result of a cost calculation: every line plus every intermediate figure,
 * so an invoice can be explained line by line and the maths re-checked later.
 * All money values are decimal strings with 2 places (never floats).
 */
final readonly class CostBreakdown
{
    /**
     * @param  list<array{type: string, description: string, spare_part_id: ?int, quantity: string, unit_price: string, total: string}>  $lines
     */
    public function __construct(
        public array $lines,
        public string $partsTotal,
        public string $partsCost,
        public string $laborTotal,
        public string $subtotal,
        public string $discount,
        public string $taxRate,
        public string $taxAmount,
        public string $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'parts_total' => $this->partsTotal,
            'labor_total' => $this->laborTotal,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
        ];
    }
}
