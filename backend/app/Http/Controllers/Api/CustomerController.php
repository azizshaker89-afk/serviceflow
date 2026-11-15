<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\ServiceRequestListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    /**
     * Paginated list with each customer's request count and billed total (SQL sub-selects).
     * Reception also uses it as a lookup while creating a request.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $customers = Customer::query()
            ->search($validated['search'] ?? null)
            ->withCount('serviceRequests')
            ->withSum(['invoices as billed_total' => fn (Builder $q) => $q->where('status', '!=', InvoiceStatus::Cancelled)], 'total')
            ->withMax('serviceRequests as last_request_at', 'created_at')
            ->orderByDesc('last_request_at')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ApiResponse::success([
            'items' => CustomerResource::collection($customers->items()),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $customer->loadCount('serviceRequests');
        $requests = $customer->serviceRequests()->with(['category', 'technician'])->latest()->limit(20)->get();
        $canSeeInvoices = $request->user()->can('invoices.view');

        return ApiResponse::success([
            'customer' => new CustomerResource($customer),
            'requests' => ServiceRequestListResource::collection($requests),
            'invoices' => $canSeeInvoices
                ? InvoiceListResource::collection($customer->invoices()->with('serviceRequest')->latest('issued_at')->limit(20)->get())
                : null,
            'balance_due' => $canSeeInvoices
                ? bcadd((string) $customer->invoices()->outstanding()->selectRaw('COALESCE(SUM(total - amount_paid), 0) as v')->value('v'), '0', 2)
                : null,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::query()->create($request->validated());
        $this->log($request, $customer, 'created', 'إضافة عميل');

        return ApiResponse::success(new CustomerResource($customer), "تمت إضافة {$customer->name}", 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());
        $this->log($request, $customer, 'updated', 'تعديل بيانات عميل', array_keys($customer->getChanges()));

        return ApiResponse::success(new CustomerResource($customer), 'تم حفظ التعديلات');
    }

    /**
     * Soft delete keeps their past requests and invoices intact; a customer with open work cannot be removed.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->serviceRequests()->open()->exists()) {
            throw new BusinessRuleException('لا يمكن حذف عميل لديه طلبات مفتوحة.');
        }

        if ($customer->invoices()->outstanding()->exists()) {
            throw new BusinessRuleException('لا يمكن حذف عميل عليه فواتير لم تُسدد.');
        }

        $customer->delete();
        $this->log($request, $customer, 'deleted', 'حذف عميل');

        return ApiResponse::success(null, 'تم حذف العميل');
    }

    /**
     * @param  list<string>  $changes
     */
    private function log(Request $request, Customer $customer, string $event, string $text, array $changes = []): void
    {
        activity('customers')->performedOn($customer)->causedBy($request->user())->event($event)
            ->withProperties($changes ? ['changes' => $changes] : [])
            ->log("{$text}: {$customer->name}");
    }
}
