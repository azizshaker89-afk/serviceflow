<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'service_request_id', 'customer_id', 'issued_by',
    'parts_total', 'parts_cost', 'labor_total', 'subtotal', 'discount',
    'tax_rate', 'tax_amount', 'total', 'due_date', 'issued_at', 'notes',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'unpaid',
        'amount_paid' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'parts_total' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'labor_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice): void {
            $invoice->invoice_number = sprintf(
                '%s-%s-%05d',
                config('serviceflow.codes.invoice_prefix'),
                $invoice->issued_at->format('Y'),
                $invoice->id,
            );
            $invoice->saveQuietly();
        });
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function balanceDue(): Attribute
    {
        return Attribute::get(fn (): string => bcsub($this->total, $this->amount_paid, 2));
    }

    /**
     * Only an invoice nobody has paid against may be cancelled; after the first payment it is fixed.
     */
    public function isCancellable(): bool
    {
        return $this->status === InvoiceStatus::Unpaid;
    }

    public function acceptsPayments(): bool
    {
        return in_array($this->status, [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid], true);
    }

    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid]);
    }
}
