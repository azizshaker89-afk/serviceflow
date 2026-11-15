<?php

namespace App\Http\Resources;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = RoleName::tryFrom((string) $this->getRoleNames()->first());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'technician_profile' => $this->whenLoaded('technicianProfile', fn () => [
                'specialization' => $this->technicianProfile?->specialization,
                'hourly_rate' => $this->technicianProfile?->hourly_rate,
            ]),
        ];
    }
}
