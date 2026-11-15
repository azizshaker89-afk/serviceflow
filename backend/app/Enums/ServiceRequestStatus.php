<?php

namespace App\Enums;

/**
 * The service request lifecycle.
 *
 * All allowed transitions live in transitions() so the workflow rules exist in one place.
 * Billing state (invoiced / paid) is intentionally NOT part of this enum: it is derived from the invoice.
 */
enum ServiceRequestStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Assigned => 'تم تعيين فني',
            self::InProgress => 'جاري التنفيذ',
            self::WaitingParts => 'بانتظار قطع غيار',
            self::Completed => 'تمت الصيانة',
            self::Cancelled => 'ملغي',
        };
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::New => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Assigned, self::InProgress, self::Cancelled],
            self::InProgress => [self::WaitingParts, self::Completed, self::Cancelled],
            self::WaitingParts => [self::InProgress, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->transitions() === [];
    }

    /**
     * Statuses in which a technician is actively responsible for the request.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::New, self::Assigned, self::InProgress, self::WaitingParts];
    }
}
