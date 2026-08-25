<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-authored marketing copy for the public pricing page, per plan
     * per language — e.g. {"max_users": {"en": "Up to 10 users", "km": "..."}}.
     * Deliberately not driven by a template computed from max_users/
     * storage_limit_gb/etc: the external marketing site can't be trusted to
     * reproduce every language's pluralization/wording correctly, so the
     * admin just writes the exact sentence per plan instead. A blank/missing
     * entry means "don't show this feature line" for that plan.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('feature_labels')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('feature_labels');
        });
    }
};
