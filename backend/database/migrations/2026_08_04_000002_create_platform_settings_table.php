<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single-row settings table for the PLATFORM's own payment collection
 * details (KHQR code + bank account) — how a tenant pays their
 * subscription. Deliberately a singleton (see PlatformSetting::current(),
 * which fetches-or-creates the one and only row) rather than a key/value
 * table: there's exactly one of these, ever, for the whole SaaS business,
 * not per-tenant. A uuid PK, like every other model here — Spatie's
 * activity_log.subject_id column is a uuid, so a bigint PK would fail
 * every audit log call that touches this model.
 *
 * No real payment gateway is integrated (see create_subscription_payments
 * migration's docblock) — KHQR in Cambodia requires PSP licensing to issue
 * programmatically, so this is a static, admin-uploaded QR image plus bank
 * details for tenants to pay via manual bank transfer; a super admin then
 * manually records the payment (AdminTenantController::renewSubscription()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('khqr_image_path')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
