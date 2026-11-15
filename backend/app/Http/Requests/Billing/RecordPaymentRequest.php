<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'method' => 'طريقة الدفع', 'reference' => 'المرجع'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.', 'amount.decimal' => 'المبلغ يقبل رقمين عشريين على الأكثر.'];
    }
}
