<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminAnalyticsService $analytics)
    {
    }

    public function stats(Request $request): JsonResponse
    {
        $from = $request->query('date_from') ?: now()->subMonths(5)->startOfMonth()->toDateString();
        $to = $request->query('date_to') ?: now()->endOfMonth()->toDateString();

        return $this->success($this->analytics->stats($from, $to));
    }
}
