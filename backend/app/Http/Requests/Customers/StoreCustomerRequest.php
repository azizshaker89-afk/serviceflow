<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\Concerns\NormalizesEgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    use NormalizesEgyptianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => $this->normalizePhone($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'name' => [$customerId ? 'sometimes' : 'required', 'string', 'min:3', 'max:100'],
            'phone' => [
                $customerId ? 'sometimes' : 'required', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX,
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الموبايل يجب أن يكون رقمًا مصريًا صحيحًا من 11 رقمًا.',
            'phone.unique' => 'رقم الموبايل مسجل لعميل آخر.',
        ];
    }
}
