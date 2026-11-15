<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockLedger;

/**
 * Manual stock changes by the admin: a purchase (always adds) or a count adjustment
 * after a stocktake (may add or remove, and must say why).
 */
class AdjustStock
{
    public function __construct(private StockLedger $stock) {}

    public function handle(SparePart $part, StockMovementType $type, int $quantity, User $actor, ?string $note = null): StockMovement
    {
        if (! in_array($type, [StockMovementType::Purchase, StockMovementType::Adjustment], true)) {
            throw new BusinessRuleException('نوع الحركة غير مسموح به يدويًا.');
        }

        if ($type === StockMovementType::Purchase && $quantity <= 0) {
            throw new BusinessRuleException('كمية التوريد يجب أن تكون أكبر من صفر.');
        }

        $movement = $this->stock->move($part, $quantity, $type, $actor, null, $note);

        activity('inventory')
            ->performedOn($part)
            ->causedBy($actor)
            ->event('stock_adjusted')
            ->withProperties(['type' => $type->value, 'quantity' => $quantity, 'balance_after' => $movement->balance_after])
            ->log("{$part->sku}: {$type->label()} {$quantity}");

        return $movement;
    }
}
