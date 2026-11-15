<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * No line items and no tax rate in the input: lines come from the request's recorded
 * parts and hours, and the tax rate from settings. The accountant only decides the discount.
 */
class StoreInvoiceRequest extends FormRequest
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
            'service_request_id' => ['required', 'integer', Rule::exists('service_requests', 'id')->whereNull('deleted_at')],
            'discount' => ['nullable', 'decimal:0,2', 'min:0'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['service_request_id' => 'الطلب', 'discount' => 'الخصم', 'due_date' => 'تاريخ الاستحقاق', 'notes' => 'الملاحظات'];
    }
}
