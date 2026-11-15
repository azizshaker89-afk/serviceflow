<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->comment('Null when the customer submitted the request online')->constrained('users')->restrictOnDelete();
            $table->string('source', 10)->default('staff');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('new');
            $table->string('priority', 10)->default('normal');
            $table->text('description');
            $table->string('address')->nullable();
            $table->date('preferred_date')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->boolean('no_parts_confirmed')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_technician_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
