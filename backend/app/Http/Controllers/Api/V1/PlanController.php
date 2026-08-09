<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated plan listing — for a pricing/marketing page
 * (this app's own, or an external website) to display available plans.
 * No API key: plan pricing/features are meant to be shown to the public,
 * and a key embedded in browser-side JS would protect nothing anyway.
 */
class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success(PlanResource::collection($plans));
    }
}
