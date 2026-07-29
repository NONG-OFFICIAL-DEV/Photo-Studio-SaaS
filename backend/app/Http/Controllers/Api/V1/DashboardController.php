<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected DashboardService $dashboard)
    {
    }

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        return $this->success($this->dashboard->stats());
    }
}
