<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->decimal('hours', 5, 2);
            $table->decimal('hourly_rate', 10, 2)->comment('Snapshot of the technician rate when logged');
            $table->string('note')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['technician_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
