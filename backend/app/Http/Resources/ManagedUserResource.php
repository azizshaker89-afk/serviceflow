<?php

namespace App\Http\Resources;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff account as the admin manages it.
 *
 * @mixin User
 */
class ManagedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = RoleName::tryFrom((string) $this->roles->first()?->name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'is_active' => $this->is_active,
            'is_me' => $request->user()?->id === $this->id,
            'specialization' => $this->technicianProfile?->specialization,
            'hourly_rate' => $this->technicianProfile?->hourly_rate,
            'open_requests_count' => $this->whenHas('open_requests_count'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
