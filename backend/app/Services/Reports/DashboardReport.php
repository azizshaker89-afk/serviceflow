<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\SparePart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the dashboard, computed live from the database.
 *
 * Counts and sums are SQL aggregates (COUNT / SUM / GROUP BY), never "load every row and
 * count in PHP". The only exception is completion time, which needs a date difference whose
 * SQL differs per database; it is averaged over the period's completed jobs only.
 */
class DashboardReport
{
    public function __construct(
        private CarbonImmutable $from,
        private CarbonImmutable $to,
    ) {}

    /**
     * @return array<string, int>
     */
    public function operationalKpis(): array
    {
        $now = CarbonImmutable::now();

        return [
            'new_today' => ServiceRequest::query()->where('created_at', '>=', $now->startOfDay())->count(),
            'new_this_week' => ServiceRequest::query()->where('created_at', '>=', $now->startOfWeek())->count(),
            'new_this_month' => ServiceRequest::query()->where('created_at', '>=', $now->startOfMonth())->count(),
            'open' => ServiceRequest::query()->open()->count(),
            'unassigned' => ServiceRequest::query()->status(ServiceRequestStatus::New)->count(),
            'overdue' => ServiceRequest::query()->overdue()->count(),
            'waiting_parts' => ServiceRequest::query()->status(ServiceRequestStatus::WaitingParts)->count(),
            'completed_in_period' => $this->completedInPeriod()->count(),
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function financialKpis(): array
    {
        $collected = Payment::query()->whereBetween('paid_at', [$this->from, $this->to])->sum('amount');
        $invoiced = Invoice::query()->where('status', '!=', InvoiceStatus::Cancelled)->whereBetween('issued_at', [$this->from, $this->to]);

        return [
            'collected_in_period' => $this->money($collected),
            'invoiced_in_period' => $this->money((clone $invoiced)->sum('total')),
            'parts_cost_in_period' => $this->money((clone $invoiced)->sum('parts_cost')),
            'outstanding' => $this->money(Invoice::query()->outstanding()->sum(DB::raw('total - amount_paid'))),
            'outstanding_invoices' => Invoice::query()->outstanding()->count(),
            'awaiting_invoice' => ServiceRequest::query()->awaitingInvoice()->count(),
        ];
    }

    public function averageCompletionHours(): ?float
    {
        $durations = $this->completedInPeriod()->get(['created_at', 'completed_at'])
            ->map(fn (ServiceRequest $r) => $r->created_at->diffInMinutes($r->completed_at) / 60);

        return $durations->isEmpty() ? null : round($durations->avg(), 1);
    }

    public function onTimeRate(): ?int
    {
        $total = $this->completedInPeriod()->count();

        if ($total === 0) {
            return null;
        }

        $onTime = $this->completedInPeriod()->whereColumn('completed_at', '<=', 'due_at')->count();

        return (int) round($onTime / $total * 100);
    }

    /**
     * Open-pipeline snapshot: how many requests sit in each status right now.
     *
     * @return list<array{status: string, label: string, count: int}>
     */
    public function statusDistribution(): array
    {
        $counts = ServiceRequest::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        return collect(ServiceRequestStatus::cases())
            ->map(fn (ServiceRequestStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])->all();
    }

    /**
     * Money collected and invoiced per month for the last six months, zero-filled.
     *
     * @return list<array{month: string, collected: string, invoiced: string}>
     */
    public function monthlyRevenue(int $months = 6): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $collected = Payment::query()->where('paid_at', '>=', $start)
            ->select(DB::raw($this->monthExpression('paid_at').' as month'), DB::raw('SUM(amount) as total'))
            ->groupBy('month')->pluck('total', 'month');

        $invoiced = Invoice::query()->where('issued_at', '>=', $start)->where('status', '!=', InvoiceStatus::Cancelled)
            ->select(DB::raw($this->monthExpression('issued_at').' as month'), DB::raw('SUM(total) as total'))
            ->groupBy('month')->pluck('total', 'month');

        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $collected, $invoiced): array {
            $month = $start->addMonths($offset)->format('Y-m');

            return [
                'month' => $month,
                'collected' => $this->money($collected[$month] ?? 0),
                'invoiced' => $this->money($invoiced[$month] ?? 0),
            ];
        })->all();
    }

    /**
     * Requests per day over the period, for the intake trend line.
     *
     * @return list<array{date: string, count: int}>
     */
    public function dailyIntake(): array
    {
        $counts = ServiceRequest::query()->whereBetween('created_at', [$this->from, $this->to])
            ->select(DB::raw($this->dayExpression('created_at').' as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')->pluck('total', 'day');

        $days = [];

        for ($day = $this->from->startOfDay(); $day->lte($this->to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $days;
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    public function topCategories(int $limit = 5): array
    {
        return ServiceRequest::query()
            ->join('service_categories', 'service_categories.id', '=', 'service_requests.service_category_id')
            ->whereBetween('service_requests.created_at', [$this->from, $this->to])
            ->groupBy('service_categories.id', 'service_categories.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get(['service_categories.name', DB::raw('COUNT(*) as total')])
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * One row per active technician. Counts come from withCount sub-selects; revenue is the
     * total of active invoices on the jobs they completed in the period.
     *
     * @return list<array<string, mixed>>
     */
    public function technicianPerformance(): array
    {
        $completedInPeriod = fn (Builder $q) => $q->where('status', ServiceRequestStatus::Completed)->whereBetween('completed_at', [$this->from, $this->to]);

        $technicians = User::query()->technicians()->active()
            ->withCount([
                'assignedServiceRequests as open_count' => fn (Builder $q) => $q->open(),
                'assignedServiceRequests as overdue_count' => fn (Builder $q) => $q->overdue(),
                'assignedServiceRequests as completed_count' => $completedInPeriod,
                'assignedServiceRequests as on_time_count' => fn (Builder $q) => $completedInPeriod($q)->whereColumn('completed_at', '<=', 'due_at'),
            ])
            ->withSum('workLogs as hours_logged', 'hours')
            ->get();

        $revenue = Invoice::query()
            ->join('service_requests', 'service_requests.id', '=', 'invoices.service_request_id')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled)
            ->whereBetween('service_requests.completed_at', [$this->from, $this->to])
            ->groupBy('service_requests.assigned_technician_id')
            ->selectRaw('service_requests.assigned_technician_id as technician_id, SUM(invoices.total) as revenue')
            ->pluck('revenue', 'technician_id');

        return $technicians
            ->map(fn (User $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'open' => $t->open_count,
                'overdue' => $t->overdue_count,
                'completed' => $t->completed_count,
                'on_time_rate' => $t->completed_count ? (int) round($t->on_time_count / $t->completed_count * 100) : null,
                'hours_logged' => $this->money($t->hours_logged ?? 0),
                'revenue' => $this->money($revenue[$t->id] ?? 0),
            ])
            ->sortByDesc('completed')
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, sku: string, quantity: int, stock: int, is_low: bool}>
     */
    public function partsUsage(int $limit = 6): array
    {
        return ServiceRequestPart::query()
            ->join('spare_parts', 'spare_parts.id', '=', 'service_request_parts.spare_part_id')
            ->whereBetween('service_request_parts.created_at', [$this->from, $this->to])
            ->groupBy('spare_parts.id', 'spare_parts.name', 'spare_parts.sku', 'spare_parts.stock_quantity', 'spare_parts.min_stock')
            ->orderByDesc('used')
            ->limit($limit)
            ->get(['spare_parts.name', 'spare_parts.sku', 'spare_parts.stock_quantity', 'spare_parts.min_stock', DB::raw('SUM(service_request_parts.quantity) as used')])
            ->map(fn ($row) => [
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => (int) $row->used,
                'stock' => (int) $row->stock_quantity,
                'is_low' => $row->stock_quantity <= $row->min_stock,
            ])->all();
    }

    public function lowStockCount(): int
    {
        return SparePart::query()->active()->lowStock()->count();
    }

    /**
     * @return list<array{id: int, name: string, requests: int}>
     */
    public function topCustomers(int $limit = 5): array
    {
        $inPeriod = fn (Builder $q) => $q->whereBetween('created_at', [$this->from, $this->to]);

        return Customer::query()
            ->whereHas('serviceRequests', $inPeriod)
            ->withCount(['serviceRequests as requests' => $inPeriod])
            ->orderByDesc('requests')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'requests' => (int) $c->requests])
            ->all();
    }

    /**
     * Requests that need someone's attention now, most urgent first.
     *
     * @return list<array<string, mixed>>
     */
    public function attentionQueue(int $limit = 6): array
    {
        return ServiceRequest::query()
            ->with(['customer', 'technician', 'category'])
            ->where(fn (Builder $q) => $q->overdue()->orWhere('status', ServiceRequestStatus::New))
            ->orderBy('due_at')
            ->limit($limit)
            ->get()
            ->map(fn (ServiceRequest $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'category' => $r->category->name,
                'customer' => $r->customer->name,
                'technician' => $r->technician?->name,
                'due_at' => $r->due_at->toIso8601String(),
                'is_overdue' => $r->is_overdue,
            ])->all();
    }

    private function completedInPeriod(): Builder
    {
        return ServiceRequest::query()
            ->where('status', ServiceRequestStatus::Completed)
            ->whereBetween('completed_at', [$this->from, $this->to]);
    }

    /**
     * Month bucketing is the one piece of SQL that differs between MySQL (production) and SQLite (tests).
     */
    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function dayExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d', {$column})" : "DATE({$column})";
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', 2);
    }
}
