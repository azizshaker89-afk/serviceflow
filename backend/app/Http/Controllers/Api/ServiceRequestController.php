<?php

namespace App\Http\Controllers\Api;

use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Enums\Permission;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequests\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequestListResource;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const SORTABLE = ['created_at' => 'created_at', 'due_at' => 'due_at', 'code' => 'id'];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ServiceRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ServiceRequestStatus::class)],
            'priority' => ['nullable', 'string'],
            'service_category_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'integer'],
            'overdue' => ['nullable', 'boolean'],
            'open' => ['nullable', 'boolean'],
            'awaiting_invoice' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTABLE))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $user = $request->user();

        $requests = ServiceRequest::query()
            ->with(['customer', 'category', 'technician'])
            ->unless(
                $user->can(Permission::ViewAllServiceRequests->value),
                fn (Builder $query) => $query->assignedTo($user),
            )
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['service_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('service_category_id', $id))
            ->when($filters['technician_id'] ?? null, fn (Builder $query, int $id) => $query->where('assigned_technician_id', $id))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->overdue())
            ->when($request->boolean('open'), fn (Builder $query) => $query->open())
            ->when($request->boolean('awaiting_invoice'), fn (Builder $query) => $query->awaitingInvoice())
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term) {
                $query->where('code', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term));
            }))
            ->orderBy(self::SORTABLE[$filters['sort'] ?? 'created_at'], $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ApiResponse::success([
            'items' => ServiceRequestListResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('view', $serviceRequest);

        $serviceRequest->load(ServiceRequestResource::RELATIONS);

        return ApiResponse::success(new ServiceRequestResource($serviceRequest));
    }

    /**
     * Reception creates a request for an existing customer, or registers the customer on the spot.
     * An existing phone number is matched to its customer instead of creating a duplicate.
     */
    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $create): JsonResponse
    {
        Gate::authorize('create', ServiceRequest::class);

        $serviceRequest = DB::transaction(function () use ($request, $create): ServiceRequest {
            $customer = $request->filled('customer_id')
                ? Customer::query()->findOrFail($request->validated('customer_id'))
                : Customer::query()->createOrFirst(
                    ['phone' => $request->validated('customer.phone')],
                    [
                        'name' => $request->validated('customer.name'),
                        'city' => $request->validated('customer.city'),
                        'address' => $request->validated('customer.address'),
                    ],
                );

            return $create->handle(
                $customer,
                $request->safe()->only(['service_category_id', 'priority', 'description', 'address', 'preferred_date']),
                $request->user(),
                RequestSource::Staff,
            );
        });

        return ApiResponse::success(
            new ServiceRequestResource($serviceRequest->fresh()->load(ServiceRequestResource::RELATIONS)),
            "تم إنشاء الطلب {$serviceRequest->fresh()->code}",
            201,
        );
    }

    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest, UpdateServiceRequest $update): JsonResponse
    {
        Gate::authorize('update', $serviceRequest);

        $update->handle($serviceRequest, $request->validated(), $request->user());

        return ApiResponse::success(
            new ServiceRequestResource($serviceRequest->fresh()->load(ServiceRequestResource::RELATIONS)),
            'تم حفظ التعديلات',
        );
    }

    /**
     * Only a request nobody has worked on can be deleted (soft delete). Anything later is cancelled instead,
     * so its history and any stock movements stay explainable.
     */
    public function destroy(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('delete', $serviceRequest);

        $serviceRequest->delete();

        activity('service-requests')
            ->performedOn($serviceRequest)
            ->causedBy($request->user())
            ->event('deleted')
            ->log("{$serviceRequest->code}: حذف الطلب");

        return ApiResponse::success(null, 'تم حذف الطلب');
    }
}
