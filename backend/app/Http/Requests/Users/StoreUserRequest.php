<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\NormalizesEgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use NormalizesEgyptianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => $this->normalizePhone($this->input('phone'))]);
        }
    }

    /**
     * A technician needs a specialization and an hourly rate: labor cost is computed from it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isTechnician = $this->input('role') === RoleName::Technician->value;

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::enum(RoleName::class)],
            'specialization' => [Rule::requiredIf($isTechnician), 'nullable', 'string', 'max:100'],
            'hourly_rate' => [Rule::requiredIf($isTechnician), 'nullable', 'decimal:0,2', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => 'الدور', 'specialization' => 'التخصص', 'hourly_rate' => 'سعر الساعة',
            'password' => 'كلمة المرور', 'email' => 'البريد الإلكتروني', 'name' => 'الاسم',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'البريد الإلكتروني مستخدم لحساب آخر.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'password.letters' => 'كلمة المرور لازم فيها حروف.',
            'password.numbers' => 'كلمة المرور لازم فيها أرقام.',
            'phone.regex' => 'رقم الموبايل يجب أن يكون رقمًا مصريًا صحيحًا من 11 رقمًا.',
        ];
    }
}
