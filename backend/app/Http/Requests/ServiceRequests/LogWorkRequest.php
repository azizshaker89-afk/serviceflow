<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Foundation\Http\FormRequest;

class LogWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Hours in quarter-hour steps, at most one working day per entry.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'numeric', 'min:0.25', 'max:12', 'multiple_of:0.25'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['hours' => 'عدد الساعات', 'note' => 'الملاحظة الفنية'];
    }
}
