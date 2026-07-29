<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('package_id');
            $table->uuid('service_id')->nullable();
            $table->uuid('addon_id')->nullable();

            $table->unsignedInteger('quantity')->default(1);

            // false = included in the package's base price (component
            // total); true = an optional extra offered alongside the
            // package but not part of its price — selected per-order.
            $table->boolean('is_optional')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('addon_id')->references('id')->on('service_addons')->cascadeOnDelete();

            $table->index(['tenant_id', 'package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_components');
    }
};
