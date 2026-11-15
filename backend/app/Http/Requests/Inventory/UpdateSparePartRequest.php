<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Stock is not editable here: quantities only change through movements (purchase / adjustment).
 */
class UpdateSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_cost' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999'],
            'unit_price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999'],
            'min_stock' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'اسم القطعة', 'unit_cost' => 'سعر التكلفة', 'unit_price' => 'سعر البيع', 'min_stock' => 'الحد الأدنى'];
    }
}
