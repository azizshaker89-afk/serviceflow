<?php

namespace App\Http\Resources;

use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkLog
 */
class WorkLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technician' => $this->technician?->name,
            'hours' => $this->hours,
            'hourly_rate' => $this->hourly_rate,
            'note' => $this->note,
            'logged_at' => $this->logged_at->toIso8601String(),
        ];
    }
}
