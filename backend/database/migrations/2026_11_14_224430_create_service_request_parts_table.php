<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->comment('Snapshot at the time of use');
            $table->decimal('unit_price', 12, 2)->comment('Snapshot at the time of use');
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['service_request_id', 'spare_part_id']);
            $table->index('spare_part_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_parts');
    }
};
