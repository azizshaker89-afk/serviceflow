<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The figures shown on the public home page, computed from real data and cached for
 * ten minutes so a busy landing page never runs these aggregates on every visit.
 */
class PublicStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember('public-stats', now()->addMinutes(10), function (): array {
            $completed = ServiceRequest::query()
                ->where('status', ServiceRequestStatus::Completed)
                ->get(['created_at', 'completed_at', 'due_at']);

            $count = $completed->count();
            $avgHours = $count ? $completed->avg(fn (ServiceRequest $r) => $r->created_at->diffInMinutes($r->completed_at) / 60) : null;
            $onTime = $count ? $completed->filter(fn (ServiceRequest $r) => $r->completed_at->lte($r->due_at))->count() : 0;

            return [
                'completed_requests' => $count,
                'average_completion_hours' => $avgHours !== null ? round($avgHours, 1) : null,
                'on_time_rate' => $count ? (int) round($onTime / $count * 100) : null,
                'technicians' => User::query()->technicians()->active()->count(),
                'sla_hours' => config('serviceflow.sla_hours'),
            ];
        });

        return ApiResponse::success($stats);
    }
}
