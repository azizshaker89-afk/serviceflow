<?php

namespace App\Enums;

enum InvoiceItemType: string
{
    case Part = 'part';
    case Labor = 'labor';

    public function label(): string
    {
        return match ($this) {
            self::Part => 'قطعة غيار',
            self::Labor => 'مصنعية',
        };
    }
}
