<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit trail. Nothing in the API edits or deletes activity rows.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewActivityLogs->value), 403);

        $validated = $request->validate([
            'group' => ['nullable', Rule::in(array_keys(ActivityResource::GROUPS))],
            'causer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $activities = Activity::query()
            ->with(['causer', 'subject' => fn ($morph) => $morph->withoutGlobalScopes()])
            ->when($validated['group'] ?? null, fn (Builder $q, string $group) => $q->where('log_name', $group))
            ->when($validated['causer_id'] ?? null, fn (Builder $q, int $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($validated['search'] ?? null, fn (Builder $q, string $term) => $q->where('description', 'like', "%{$term}%"))
            ->when($validated['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return ApiResponse::success([
            'items' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'total' => $activities->total(),
            ],
            'groups' => collect(ActivityResource::GROUPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
