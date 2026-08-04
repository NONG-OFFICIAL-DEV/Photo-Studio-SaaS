<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformSettingRequest;
use App\Http\Resources\PlatformSettingResource;
use App\Models\PlatformSetting;
use App\Services\PlatformSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated purely by the `super-admin` route middleware, same convention as
 * AdminPlanController/AdminRolePermissionController.
 */
class AdminPlatformSettingController extends Controller
{
    use ApiResponse;

    public function __construct(protected PlatformSettingService $settings)
    {
    }

    public function show(): JsonResponse
    {
        return $this->success(new PlatformSettingResource(PlatformSetting::current()));
    }

    public function update(UpdatePlatformSettingRequest $request): JsonResponse
    {
        $setting = $this->settings->update($request->validated());

        return $this->success(new PlatformSettingResource($setting), 'Payment settings updated successfully.');
    }

    public function uploadKhqr(Request $request): JsonResponse
    {
        $request->validate(['khqr_image' => ['required', 'image', 'max:2048']]);

        $setting = $this->settings->uploadKhqr($request->file('khqr_image'));

        return $this->success(new PlatformSettingResource($setting), 'KHQR image uploaded successfully.');
    }
}
