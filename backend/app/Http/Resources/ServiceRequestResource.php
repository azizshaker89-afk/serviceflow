<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends ServiceRequestListResource
{
    /**
     * Everything toArray() reads, loaded in one go to avoid N+1 queries.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'customer', 'category', 'technician', 'creator',
        'statusHistories.changedBy', 'workLogs.technician', 'parts.sparePart', 'parts.addedBy', 'invoice',
    ];

    /**
     * Policy abilities exposed to the UI. The API enforces them; the UI only hides what is not allowed.
     *
     * @var list<string>
     */
    private const ABILITIES = [
        'update', 'assign', 'start', 'pause', 'resume', 'manageParts',
        'logWork', 'complete', 'cancel', 'issueInvoice', 'delete',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workLogs = $this->whenLoaded('workLogs');
        $parts = $this->whenLoaded('parts');

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'service_category_id' => $this->service_category_id,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'no_parts_confirmed' => $this->no_parts_confirmed,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name ?? 'العميل (أونلاين)'),
            'history' => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'work_logs' => WorkLogResource::collection($workLogs),
            'parts' => ServiceRequestPartResource::collection($parts),
            'totals' => $this->when($this->relationLoaded('workLogs') && $this->relationLoaded('parts'), fn () => [
                'hours' => number_format((float) $this->workLogs->sum('hours'), 2, '.', ''),
                'parts_price' => number_format((float) $this->parts->sum(fn ($line) => $line->line_total), 2, '.', ''),
            ]),
            'has_invoice' => $this->whenLoaded('invoice', fn () => $this->invoice !== null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->id,
                'number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status->value,
                'status_label' => $this->invoice->status->label(),
                'total' => $this->invoice->total,
            ] : null),
            'allowed_actions' => $this->allowedActions($request),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedActions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return collect(self::ABILITIES)
            ->filter(fn (string $ability) => $user->can($ability, $this->resource))
            ->map(fn (string $ability) => Str::snake($ability))
            ->values()
            ->all();
    }
}
