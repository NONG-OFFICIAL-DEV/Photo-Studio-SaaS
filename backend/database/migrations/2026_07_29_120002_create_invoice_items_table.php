<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->uuid('service_id')->nullable();
            $table->uuid('addon_id')->nullable();

            // Snapshotted at invoice time so a later catalog price/name
            // change (or deletion) never rewrites history on past invoices.
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('addon_id')->references('id')->on('service_addons')->nullOnDelete();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
