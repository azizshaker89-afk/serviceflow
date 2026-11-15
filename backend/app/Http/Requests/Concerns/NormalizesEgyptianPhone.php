<?php

namespace App\Http\Requests\Concerns;

/**
 * Accepts what people actually type: Arabic-Indic digits, spaces, dashes and a +20 / 0020 prefix,
 * and stores one canonical form (01XXXXXXXXX) so lookups by phone are reliable.
 */
trait NormalizesEgyptianPhone
{
    public const EGYPTIAN_MOBILE_REGEX = '/^01[0125][0-9]{8}$/';

    protected function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        return preg_replace('/^(\+20|0020)/', '0', $phone);
    }
}
