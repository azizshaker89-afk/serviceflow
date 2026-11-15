<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'user' => $this->user?->name,
            'note' => $this->note,
            'service_request' => $this->reference instanceof ServiceRequest
                ? ['id' => $this->reference->id, 'code' => $this->reference->code]
                : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
