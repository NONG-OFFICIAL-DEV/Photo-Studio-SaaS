<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit')->default('unit');
            $table->string('category')->nullable();

            // Always recomputed as sum(stock_in) - sum(stock_out) from this
            // item's movements — never hand-edited directly — so it can
            // never drift from the audit trail that produced it.
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_threshold', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
