<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /** @var array<string, string> */
    public const GROUPS = [
        'service-requests' => 'الطلبات',
        'inventory' => 'المخزون',
        'billing' => 'الفواتير',
        'customers' => 'العملاء',
        'users' => 'المستخدمون',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->log_name,
            'group_label' => self::GROUPS[$this->log_name] ?? $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'causer' => $this->causer instanceof User ? $this->causer->name : 'النظام / العميل',
            'subject' => $this->subjectLink(),
            'properties' => $this->properties,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Where the UI can take the reader to see the record this entry is about.
     *
     * @return array{type: string, id: int, label: string}|null
     */
    private function subjectLink(): ?array
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof ServiceRequest => ['type' => 'service_request', 'id' => $subject->id, 'label' => $subject->code],
            $subject instanceof Invoice => ['type' => 'invoice', 'id' => $subject->id, 'label' => (string) $subject->invoice_number],
            $subject instanceof Customer => ['type' => 'customer', 'id' => $subject->id, 'label' => $subject->name],
            $subject instanceof SparePart => ['type' => 'spare_part', 'id' => $subject->id, 'label' => $subject->sku],
            $subject instanceof User => ['type' => 'user', 'id' => $subject->id, 'label' => $subject->name],
            default => null,
        };
    }
}
