<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only code allowed to change spare_parts.stock_quantity.
 *
 * Every change locks the part row (SELECT ... FOR UPDATE), checks the balance can't go negative,
 * updates it and appends a StockMovement in the same transaction. Two technicians taking the last
 * unit at the same moment are serialized by the row lock: the second one sees the new balance.
 */
class StockLedger
{
    public function move(
        SparePart $part,
        int $quantity,
        StockMovementType $type,
        User $user,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new BusinessRuleException('كمية الحركة لا يمكن أن تكون صفرًا.');
        }

        return DB::transaction(function () use ($part, $quantity, $type, $user, $reference, $note): StockMovement {
            $locked = SparePart::query()->withTrashed()->lockForUpdate()->findOrFail($part->id);
            $balance = $locked->stock_quantity + $quantity;

            if ($balance < 0) {
                throw new BusinessRuleException(
                    "الكمية غير متاحة. المتاح من «{$locked->name}» في المخزن {$locked->stock_quantity} فقط."
                );
            }

            $locked->stock_quantity = $balance;
            $locked->save();
            $part->setRawAttributes($locked->getAttributes(), true);

            return $locked->stockMovements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $user->id,
                'note' => $note,
            ]);
        });
    }
}
