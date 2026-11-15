<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\NormalizesEgyptianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class TrackServiceRequestRequest extends FormRequest
{
    use NormalizesEgyptianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'phone' => $this->normalizePhone($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'regex:'.self::EGYPTIAN_MOBILE_REGEX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'اكتب رقم الموبايل الذي سجلت به الطلب.',
        ];
    }
}
