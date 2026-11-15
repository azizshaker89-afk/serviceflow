<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelInvoiceRequest;
use App\Http\Requests\Billing\RecordPaymentRequest;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'outstanding' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = Invoice::query()
            ->with(['customer', 'serviceRequest'])
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->boolean('outstanding'), fn (Builder $q) => $q->outstanding())
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('issued_at', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('serviceRequest', fn (Builder $r) => $r->where('code', 'like', "%{$term}%"))
                    ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
            }));

        $invoices = (clone $query)->latest('issued_at')->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return ApiResponse::success([
            'items' => InvoiceListResource::collection($invoices->items()),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
            'summary' => [
                'outstanding_total' => Invoice::query()->outstanding()->selectRaw('COALESCE(SUM(total - amount_paid), 0) as v')->value('v'),
                'outstanding_count' => Invoice::query()->outstanding()->count(),
                'awaiting_invoice_count' => ServiceRequest::query()->awaitingInvoice()->count(),
            ],
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        return ApiResponse::success(new InvoiceResource($invoice->load(InvoiceResource::RELATIONS)));
    }

    /**
     * What the invoice would contain for a given discount, computed by the same calculator, nothing saved.
     */
    public function preview(Request $request, ServiceRequest $serviceRequest, IssueInvoice $issue): JsonResponse
    {
        Gate::authorize('issueInvoice', $serviceRequest);

        $discount = $request->validate(['discount' => ['nullable', 'decimal:0,2', 'min:0']])['discount'] ?? '0';

        return ApiResponse::success($issue->preview($serviceRequest, (string) $discount)->toArray());
    }

    public function store(StoreInvoiceRequest $request, IssueInvoice $issue): JsonResponse
    {
        $serviceRequest = ServiceRequest::query()->findOrFail($request->validated('service_request_id'));
        Gate::authorize('issueInvoice', $serviceRequest);

        $invoice = $issue->handle(
            $serviceRequest,
            $request->user(),
            (string) ($request->validated('discount') ?? '0'),
            $request->validated('due_date'),
            $request->validated('notes'),
        );

        return ApiResponse::success(
            new InvoiceResource($invoice->load(InvoiceResource::RELATIONS)),
            "تم إصدار الفاتورة {$invoice->invoice_number}",
            201,
        );
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, RecordPayment $record): JsonResponse
    {
        Gate::authorize('recordPayment', $invoice);

        $record->handle(
            $invoice,
            (string) $request->validated('amount'),
            PaymentMethod::from($request->validated('method')),
            $request->user(),
            $request->validated('reference'),
            $request->validated('notes'),
        );

        $invoice->refresh();

        return ApiResponse::success(
            new InvoiceResource($invoice->load(InvoiceResource::RELATIONS)),
            $invoice->status === InvoiceStatus::Paid ? 'تم تسجيل الدفعة. الفاتورة مدفوعة بالكامل.' : 'تم تسجيل الدفعة',
        );
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice, CancelInvoice $cancel): JsonResponse
    {
        Gate::authorize('cancel', $invoice);

        $cancel->handle($invoice, $request->user(), $request->validated('reason'));

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()->load(InvoiceResource::RELATIONS)),
            'تم إلغاء الفاتورة. يمكن الآن إصدار فاتورة جديدة للطلب.',
        );
    }
}
