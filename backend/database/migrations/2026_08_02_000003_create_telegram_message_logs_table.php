<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A permanent record of every Telegram send attempt — the toast shown
     * at the moment of sending is the only feedback that existed before
     * this, so a staff member who missed it (or wasn't the one who sent
     * it) had no way to check "did that invoice/photos/package actually
     * reach the customer?" afterward.
     *
     * customer_name/sent_by_name are denormalized snapshots (not just a
     * join through customer_id/sent_by) so a log entry stays meaningful
     * even if the customer or staff member is later renamed or deleted —
     * this is a history record, not a live view.
     */
    public function up(): void
    {
        Schema::create('telegram_message_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id')->nullable();
            $table->string('customer_name');

            $table->string('type'); // invoice | album | package
            $table->string('subject_label')->nullable(); // e.g. invoice number, package name
            $table->string('format')->nullable(); // pdf | image | text — invoices only

            $table->string('status'); // sent | failed
            $table->text('error_message')->nullable();

            $table->uuid('sent_by')->nullable();
            $table->string('sent_by_name')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_message_logs');
    }
};
