<?php

namespace App\Console\Commands;

use App\Actions\Inventory\AddPartToRequest;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Test helper for StockConcurrencyTest: issues one part from a separate PHP process,
 * so several of these started at once really compete for the same stock row.
 */
#[Signature('inventory:race-probe {request} {part} {technician} {quantity=1}')]
#[Description('Internal: issue a part from its own process (used by the concurrency test).')]
class InventoryRaceProbe extends Command
{
    protected $hidden = true;

    public function handle(AddPartToRequest $issue): int
    {
        try {
            $issue->handle(
                ServiceRequest::query()->findOrFail($this->argument('request')),
                SparePart::query()->findOrFail($this->argument('part')),
                (int) $this->argument('quantity'),
                User::query()->findOrFail($this->argument('technician')),
            );
            $this->line('issued');
        } catch (BusinessRuleException) {
            $this->line('refused');
        }

        return self::SUCCESS;
    }
}
