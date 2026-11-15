<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Foundation\Http\FormRequest;

class PauseServiceRequestRequest extends FormRequest
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
        return ['waiting_for' => ['required', 'string', 'min:3', 'max:200']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['waiting_for' => 'القطعة المنتظرة'];
    }
}
