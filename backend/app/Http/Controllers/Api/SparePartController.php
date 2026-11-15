<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\AdjustStock;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\StoreSparePartRequest;
use App\Http\Requests\Inventory\UpdateSparePartRequest;
use App\Http\Resources\SparePartResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Responses\ApiResponse;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SparePartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SparePart::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'low_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $parts = SparePart::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")
            ))
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->lowStock())
            ->when($request->boolean('active'), fn (Builder $query) => $query->active())
            ->withSum('serviceRequestParts as used_quantity', 'quantity')
            ->orderByRaw('stock_quantity <= min_stock desc')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25);

        return ApiResponse::success([
            'items' => SparePartResource::collection($parts->items()),
            'meta' => [
                'current_page' => $parts->currentPage(),
                'last_page' => $parts->lastPage(),
                'total' => $parts->total(),
                'low_stock_count' => SparePart::query()->active()->lowStock()->count(),
            ],
        ]);
    }

    /**
     * Opening stock is recorded as a purchase movement, never written straight into the balance.
     */
    public function store(StoreSparePartRequest $request, AdjustStock $adjust): JsonResponse
    {
        Gate::authorize('create', SparePart::class);

        $part = DB::transaction(function () use ($request, $adjust): SparePart {
            $part = SparePart::query()->create($request->safe()->except('opening_stock'));

            if ($request->integer('opening_stock') > 0) {
                $adjust->handle($part, StockMovementType::Purchase, $request->integer('opening_stock'), $request->user(), 'رصيد افتتاحي');
            }

            return $part;
        });

        return ApiResponse::success(new SparePartResource($part->fresh()), "تمت إضافة «{$part->name}»", 201);
    }

    public function update(UpdateSparePartRequest $request, SparePart $sparePart): JsonResponse
    {
        Gate::authorize('update', $sparePart);

        $sparePart->update($request->validated());

        activity('inventory')->performedOn($sparePart)->causedBy($request->user())->event('updated')
            ->withProperties(['changes' => array_keys($sparePart->getChanges())])
            ->log("{$sparePart->sku}: تعديل بيانات القطعة");

        return ApiResponse::success(new SparePartResource($sparePart), 'تم حفظ التعديلات');
    }

    public function adjustStock(AdjustStockRequest $request, SparePart $sparePart, AdjustStock $adjust): JsonResponse
    {
        Gate::authorize('adjustStock', $sparePart);

        $type = StockMovementType::from($request->validated('type'));
        $movement = $adjust->handle($sparePart, $type, $request->integer('quantity'), $request->user(), $request->validated('note'));

        return ApiResponse::success(
            new SparePartResource($sparePart->fresh()),
            "{$type->label()}: الرصيد الآن {$movement->balance_after}",
        );
    }

    public function movements(SparePart $sparePart): JsonResponse
    {
        Gate::authorize('viewMovements', $sparePart);

        $movements = $sparePart->stockMovements()->with(['user', 'reference'])->latest('id')->limit(100)->get();

        return ApiResponse::success([
            'part' => new SparePartResource($sparePart),
            'movements' => StockMovementResource::collection($movements),
        ]);
    }
}
