<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Lightweight tenant staff list — currently just backs the
     * "assign photographer" picker on Bookings. Full user management
     * (invite/deactivate/roles UI) is a later phase.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.view'), 403);

        $users = User::query()->orderBy('name')->get();

        return $this->success(UserResource::collection($users));
    }
}
