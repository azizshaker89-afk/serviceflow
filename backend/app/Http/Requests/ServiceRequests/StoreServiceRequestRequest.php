<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\Priority;
use App\Http\Requests\Concerns\NormalizesEgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reception creates a request either for an existing customer (customer_id)
 * or for a new one typed in on the spot (customer.*).
 */
class StoreServiceRequestRequest extends FormRequest
{
    use NormalizesEgyptianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('customer'))) {
            $this->merge(['customer' => [
                ...$this->input('customer'),
                'phone' => $this->normalizePhone($this->input('customer.phone')),
            ]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer' => ['required_without:customer_id', 'nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'min:3', 'max:100'],
            'customer.phone' => ['required_with:customer', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'service_category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required_without' => 'اختر عميلًا موجودًا أو أدخل بيانات عميل جديد.',
            'customer.phone.regex' => 'رقم الموبايل يجب أن يكون رقمًا مصريًا صحيحًا من 11 رقمًا.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer.name' => 'اسم العميل',
            'customer.phone' => 'موبايل العميل',
        ];
    }
}
