<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editing_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->uuid('assigned_user_id')->nullable();

            $table->string('status')->default('pending'); // App\Enums\EditingStatus
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->uuid('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique('order_id');
            $table->index(['tenant_id', 'assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editing_tasks');
    }
};
