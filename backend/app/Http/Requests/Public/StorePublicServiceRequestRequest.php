<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\NormalizesEgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicServiceRequestRequest extends FormRequest
{
    use NormalizesEgyptianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizePhone($this->input('phone'))]);
    }

    /**
     * "website" is a honeypot: hidden from people by CSS, filled in by bots.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'service_category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:+30 days'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الموبايل يجب أن يكون رقمًا مصريًا صحيحًا من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015.',
            'website.prohibited' => 'تعذر إرسال الطلب.',
        ];
    }
}
