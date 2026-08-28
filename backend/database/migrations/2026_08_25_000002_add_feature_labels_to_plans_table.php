<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-authored marketing copy for the public pricing page — a freeform,
     * independent-per-plan array of feature rows, each with its own label
     * and value in both languages, e.g.
     * [{"key": "...", "label": {"en": "Users", "km": "..."}, "value": {"en": "Up to 20", "km": "..."}}].
     * Deliberately not driven by a template computed from max_users/
     * storage_limit_gb/etc: the external marketing site can't be trusted to
     * reproduce every language's pluralization/wording correctly, so the
     * admin just writes the exact rows per plan instead. An empty/missing
     * array means "fall back to auto-derived feature text" on the display side.
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
