<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A technician as the dispatcher sees them: specialty, availability and current load.
 *
 * @mixin User
 */
class TechnicianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'specialization' => $this->technicianProfile?->specialization,
            'is_available' => (bool) $this->technicianProfile?->is_available,
            'open_requests_count' => (int) ($this->open_requests_count ?? 0),
            'overdue_requests_count' => (int) ($this->overdue_requests_count ?? 0),
        ];
    }
}
