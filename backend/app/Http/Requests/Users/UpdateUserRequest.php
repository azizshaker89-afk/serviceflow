<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $becomesTechnician = $this->input('role', $user->getRoleNames()->first()) === RoleName::Technician->value;
        $needsProfile = $becomesTechnician && $user->technicianProfile === null;

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', Rule::enum(RoleName::class)],
            'is_active' => ['sometimes', 'boolean'],
            'specialization' => [Rule::requiredIf($needsProfile), 'sometimes', 'nullable', 'string', 'max:100'],
            'hourly_rate' => [Rule::requiredIf($needsProfile), 'sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:10000'],
        ];
    }
}
