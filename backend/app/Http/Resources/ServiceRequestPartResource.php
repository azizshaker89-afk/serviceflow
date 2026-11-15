<?php

namespace App\Http\Resources;

use App\Models\ServiceRequestPart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * unit_cost is internal: only people who handle money see it.
 *
 * @mixin ServiceRequestPart
 */
class ServiceRequestPartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spare_part_id' => $this->spare_part_id,
            'name' => $this->sparePart?->name,
            'sku' => $this->sparePart?->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'unit_cost' => $this->when($request->user()?->can('invoices.view') ?? false, $this->unit_cost),
            'added_by' => $this->addedBy?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
