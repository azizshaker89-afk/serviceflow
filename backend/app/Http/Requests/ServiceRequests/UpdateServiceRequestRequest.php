<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequestRequest extends FormRequest
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
            'service_category_id' => ['sometimes', 'integer', Rule::exists('service_categories', 'id')->where('is_active', true)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'description' => ['sometimes', 'string', 'min:10', 'max:1000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferred_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
