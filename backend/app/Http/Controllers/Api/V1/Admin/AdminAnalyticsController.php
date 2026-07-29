<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminAnalyticsService $analytics)
    {
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->analytics->stats());
    }
}
