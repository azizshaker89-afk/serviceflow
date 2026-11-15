<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TechnicianResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    /**
     * Technicians with their live workload, so the dispatcher can pick the least loaded one.
     * Counts are computed in SQL (withCount), not by loading every request.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::AssignServiceRequests->value), 403);

        $technicians = User::query()
            ->technicians()
            ->active()
            ->with('technicianProfile')
            ->withCount([
                'assignedServiceRequests as open_requests_count' => fn (Builder $query) => $query->whereIn('status', ServiceRequestStatus::open()),
                'assignedServiceRequests as overdue_requests_count' => fn (Builder $query) => $query->overdue(),
            ])
            ->orderBy('open_requests_count')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(TechnicianResource::collection($technicians));
    }
}
