<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Services\DataExportService;
use App\Services\TenantSettingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(protected TenantSettingsService $settings, protected DataExportService $dataExport)
    {
    }

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tenant.settings.manage'), 403);

        return $this->success(new TenantResource($request->user()->tenant));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $tenant = $this->settings->update($request->user()->tenant, $request->validated());

        return $this->success(new TenantResource($tenant), 'Settings updated successfully.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tenant.settings.manage'), 403);

        $request->validate(['logo' => ['required', 'image', 'max:2048']]);

        $tenant = $this->settings->uploadLogo($request->user()->tenant, $request->file('logo'));

        return $this->success(new TenantResource($tenant), 'Logo uploaded successfully.');
    }

    public function uploadQrPayment(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tenant.settings.manage'), 403);

        $request->validate(['qr_payment' => ['required', 'image', 'max:2048']]);

        $tenant = $this->settings->uploadQrPayment($request->user()->tenant, $request->file('qr_payment'));

        return $this->success(new TenantResource($tenant), 'QR payment image uploaded successfully.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('tenant.settings.manage'), 403);

        $path = $this->dataExport->buildZip();

        return response()->download($path, 'data-export-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend(true);
    }
}
