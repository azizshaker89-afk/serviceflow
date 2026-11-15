<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\SparePart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cost price is internal: only people who manage stock or money see it.
 *
 * @mixin SparePart
 */
class SparePartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $seesCost = $user?->can(Permission::ManageSpareParts->value) || $user?->can(Permission::ViewInvoices->value);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'unit_cost' => $this->when((bool) $seesCost, $this->unit_cost),
            'stock_quantity' => $this->stock_quantity,
            'min_stock' => $this->min_stock,
            'is_low_stock' => $this->stock_quantity <= $this->min_stock,
            'is_active' => $this->is_active,
            'used_quantity' => $this->whenHas('used_quantity', fn () => (int) $this->used_quantity),
        ];
    }
}
