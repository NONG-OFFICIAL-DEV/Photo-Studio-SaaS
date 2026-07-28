<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender')->nullable(); // App\Enums\CustomerGender
            $table->string('avatar_path')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();

            $table->uuid('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_favorite']);
            $table->index(['tenant_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
