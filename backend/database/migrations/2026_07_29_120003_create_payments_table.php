<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');

            $table->decimal('amount', 12, 2);
            $table->string('method')->default('cash'); // App\Enums\PaymentMethod
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('recorded_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
