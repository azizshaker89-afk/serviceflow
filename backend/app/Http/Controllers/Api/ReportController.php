<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Reports\DashboardReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * One endpoint for the dashboard. Sections are included per permission:
     * operations for the admin, money for the admin and the accountant.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $operational = $user->can(Permission::ViewOperationalReports->value);
        $financial = $user->can(Permission::ViewFinancialReports->value);

        abort_unless($operational || $financial, 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : $to->subDays(29)->startOfDay();

        abort_if($from->diffInDays($to) > 366, 422, 'أقصى فترة للتقرير سنة واحدة.');

        $report = new DashboardReport($from, $to);

        return ApiResponse::success(array_filter([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'operations' => $operational ? [
                'kpis' => $report->operationalKpis(),
                'average_completion_hours' => $report->averageCompletionHours(),
                'on_time_rate' => $report->onTimeRate(),
                'status_distribution' => $report->statusDistribution(),
                'daily_intake' => $report->dailyIntake(),
                'top_categories' => $report->topCategories(),
                'technicians' => $report->technicianPerformance(),
                'parts_usage' => $report->partsUsage(),
                'low_stock_count' => $report->lowStockCount(),
                'top_customers' => $report->topCustomers(),
                'attention' => $report->attentionQueue(),
            ] : null,
            'finance' => $financial ? [
                'kpis' => $report->financialKpis(),
                'monthly_revenue' => $report->monthlyRevenue(),
            ] : null,
        ], fn ($section) => $section !== null));
    }
}
