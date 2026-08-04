<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's "I've paid" claim after a manual bank transfer against the
 * platform's KHQR/bank details (see PlatformSetting) — closes the loop
 * between "tenant sent money" and "super admin noticed and renewed it"
 * without needing a real payment gateway. Confirming a claim calls the
 * existing SubscriptionService::renew() (same as the admin's manual
 * Renew button) and links the SubscriptionPayment it creates via
 * linked_payment_id; rejecting just records that an admin looked and
 * didn't see the money, with no side effects on the subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');

            $table->decimal('claimed_amount', 10, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();

            $table->string('status')->default('pending'); // App\Enums\PaymentConfirmationStatus

            $table->uuid('submitted_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('linked_payment_id')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('linked_payment_id')->references('id')->on('subscription_payments')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_confirmations');
    }
};
