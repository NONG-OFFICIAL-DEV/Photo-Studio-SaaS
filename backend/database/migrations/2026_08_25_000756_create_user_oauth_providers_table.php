<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_oauth_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('provider'); // 'google', extensible to future providers
            $table->string('provider_user_id'); // e.g. Google's `sub` claim
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_providers');
    }
};
