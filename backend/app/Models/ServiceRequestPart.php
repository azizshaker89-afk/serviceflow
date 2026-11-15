<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'spare_part_id', 'quantity', 'unit_cost', 'unit_price', 'added_by'])]
class ServiceRequestPart extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class)->withTrashed();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): string => bcmul((string) $this->quantity, $this->unit_price, 2));
    }
}
