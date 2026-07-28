<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id');

            $table->string('status')->default('trial'); // App\Enums\SubscriptionStatus
            $table->string('billing_cycle')->nullable(); // App\Enums\BillingCycle

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->decimal('amount', 10, 2)->nullable();
            $table->jsonb('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->restrictOnDelete();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
