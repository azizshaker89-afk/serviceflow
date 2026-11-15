<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A purchase only adds stock; a stocktake adjustment can go either way and must explain itself.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['purchase', 'adjustment'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000', Rule::when($this->input('type') === 'purchase', ['min:1'])],
            'note' => [Rule::requiredIf($this->input('type') === 'adjustment'), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'نوع الحركة', 'quantity' => 'الكمية', 'note' => 'سبب التسوية'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['quantity.not_in' => 'الكمية لا يمكن أن تكون صفرًا.'];
    }
}
