<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger: rows are never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity')->comment('Signed: positive adds stock, negative removes it');
            $table->unsignedInteger('balance_after');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['spare_part_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
