<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simulated billing ledger — one row per renewal/plan-change payment.
     * No real payment gateway is integrated; a payment row is created the
     * moment a tenant (self-service) or an admin (on the tenant's behalf)
     * "pays" for a period, and simply extends the parent Subscription's
     * current_period_ends_at. recorded_by is null for tenant self-service,
     * or the acting admin's user id.
     */
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');
            $table->uuid('plan_id');

            $table->decimal('amount', 10, 2);
            $table->string('billing_cycle'); // App\Enums\BillingCycle

            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('paid_at');

            $table->uuid('recorded_by')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->restrictOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
