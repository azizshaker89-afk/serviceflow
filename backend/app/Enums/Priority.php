<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'منخفضة',
            self::Normal => 'عادية',
            self::High => 'عالية',
            self::Urgent => 'عاجلة',
        };
    }

    /**
     * The SLA window used to compute a request's due_at.
     */
    public function slaHours(): int
    {
        return (int) config("serviceflow.sla_hours.{$this->value}");
    }
}
