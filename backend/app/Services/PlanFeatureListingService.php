<?php

namespace App\Services;

use App\Enums\PlanFeatureValueType;
use App\Models\Plan;
use App\Models\PlanFeatureListing;
use App\Repositories\Contracts\PlanFeatureListingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PlanFeatureListingService extends BaseService
{
    public function __construct(protected PlanFeatureListingRepositoryInterface $listings)
    {
        parent::__construct($listings);
    }

    public function all(): Collection
    {
        return $this->listings->all();
    }

    public function create(array $data): PlanFeatureListing
    {
        return $this->listings->create($data);
    }

    public function update(PlanFeatureListing $listing, array $data): PlanFeatureListing
    {
        return $this->listings->update($listing, $data);
    }

    /**
     * Soft delete only — a plan referencing this key in its own
     * `feature_labels` JSON blob is not blocked or cleaned up here. There's
     * no FK (the reference lives inside JSON), so the "stale key becomes
     * inert" guarantee is purely application-level: resolveForPlan() below
     * silently skips any key no longer present in the active catalog. That
     * skip is load-bearing, not incidental — don't remove it while "cleaning
     * up" this method.
     */
    public function delete(PlanFeatureListing $listing): bool
    {
        return $this->listings->delete($listing);
    }

    /**
     * Joins a plan's raw `feature_labels` map against the live active
     * catalog (ordered for display) so callers get a ready-to-render list —
     * label text, value type, and this plan's value, no guessing required.
     * A catalog key the plan hasn't saved a value for yet (e.g. added after
     * the plan was last saved) defaults per type: false for boolean,
     * empty text for text. That default intentionally makes "not set" and
     * "explicitly false" indistinguishable for booleans — fine at the
     * current boolean|text scope; revisit if a third value type is added.
     */
    public function resolveForPlan(Plan $plan): array
    {
        $values = $plan->feature_labels ?? [];

        return $this->listings->query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (PlanFeatureListing $listing) use ($values) {
                $isBoolean = $listing->value_type === PlanFeatureValueType::Boolean;
                $default = $isBoolean ? false : ['en' => '', 'km' => null];

                return [
                    'key' => $listing->key,
                    'value_type' => $listing->value_type->value,
                    'label' => ['en' => $listing->label_en, 'km' => $listing->label_km],
                    'value' => $values[$listing->key] ?? $default,
                ];
            })
            ->values()
            ->all();
    }
}
