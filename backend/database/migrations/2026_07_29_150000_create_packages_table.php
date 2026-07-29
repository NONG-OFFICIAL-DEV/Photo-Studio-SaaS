<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('name');
            $table->text('description')->nullable();

            // Final price = override_price if set, else (live component
            // total - discount). Nothing here is ever cached/snapshotted —
            // it's recomputed from the current catalog every time the
            // package is read, so editing a component's price or the
            // package's own discount always shows up immediately.
            $table->string('discount_type')->nullable(); // App\Enums\DiscountType
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('override_price', 10, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->uuid('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
