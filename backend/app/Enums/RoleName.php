<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Technician = 'technician';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Receptionist => 'موظف استقبال',
            self::Technician => 'فني صيانة',
            self::Accountant => 'محاسب',
        };
    }
}
