<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
    }

    /**
     * Selling below cost is allowed (promotions happen) but price and cost are both required.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', Rule::unique('spare_parts', 'sku')],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
            'unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:10000'],
            'opening_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => 'كود القطعة', 'name' => 'اسم القطعة', 'unit_cost' => 'سعر التكلفة',
            'unit_price' => 'سعر البيع', 'min_stock' => 'الحد الأدنى', 'opening_stock' => 'الرصيد الافتتاحي',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sku.regex' => 'كود القطعة يقبل حروفًا إنجليزية كبيرة وأرقامًا وشرطة فقط.'];
    }
}
