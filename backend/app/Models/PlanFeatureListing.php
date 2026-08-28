<?php

namespace App\Models;

use App\Enums\PlanFeatureValueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog of marketing feature rows shown on the public pricing page —
 * display copy only, defined once here and referenced by every Plan's
 * `feature_labels` (keyed by this model's `key`). Never read by
 * entitlement/gating logic — that's Plan's own has_* / max_* columns via
 * App\Http\Middleware\EnsurePlanFeature.
 */
class PlanFeatureListing extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['key', 'label_en', 'label_km', 'value_type', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'value_type' => PlanFeatureValueType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
