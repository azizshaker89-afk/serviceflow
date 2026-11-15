<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'توريد',
            self::Usage => 'صرف لطلب صيانة',
            self::Return => 'مرتجع',
            self::Adjustment => 'تسوية جرد',
        };
    }
}
