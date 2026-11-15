<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Status is guarded on purpose: it may only change through the workflow actions,
 * which validate the transition and write the status history in one transaction.
 */
#[Fillable([
    'customer_id', 'service_category_id', 'created_by', 'source', 'priority',
    'description', 'address', 'preferred_date', 'due_at',
])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'new',
        'priority' => 'normal',
        'source' => 'staff',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'priority' => Priority::class,
            'source' => RequestSource::class,
            'preferred_date' => 'date',
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_parts_confirmed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $request): void {
            $request->due_at ??= now()->addHours($request->priority->slaHours());
        });

        static::created(function (ServiceRequest $request): void {
            $request->code = sprintf(
                '%s-%s-%05d',
                config('serviceflow.codes.service_request_prefix'),
                $request->created_at->format('Y'),
                $request->id,
            );
            $request->saveQuietly();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class)->latest('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ServiceRequestPart::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    /**
     * The active invoice. A cancelled invoice stays in invoices() as history,
     * and a corrected one can be issued after it.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', InvoiceStatus::Cancelled->value);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->status, ServiceRequestStatus::open(), true)
            && $this->due_at->isPast());
    }

    #[Scope]
    protected function status(Builder $query, ServiceRequestStatus ...$statuses): void
    {
        $query->whereIn('status', $statuses);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', ServiceRequestStatus::open());
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereIn('status', ServiceRequestStatus::open())->where('due_at', '<', now());
    }

    /**
     * Completed jobs the accountant still has to bill.
     */
    #[Scope]
    protected function awaitingInvoice(Builder $query): void
    {
        $query->where('status', ServiceRequestStatus::Completed)->whereDoesntHave('invoice');
    }

    #[Scope]
    protected function assignedTo(Builder $query, User $technician): void
    {
        $query->whereBelongsTo($technician, 'technician');
    }
}
