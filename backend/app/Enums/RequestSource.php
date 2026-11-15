<?php

namespace App\Enums;

enum RequestSource: string
{
    case Staff = 'staff';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'عن طريق الاستقبال',
            self::Online => 'طلب أونلاين من العميل',
        };
    }
}
