<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantSettingsService
{
    public function update(Tenant $tenant, array $data): Tenant
    {
        $companyFields = array_intersect_key($data, array_flip([
            'name', 'email', 'phone', 'address', 'currency', 'timezone',
        ]));

        $settingsFields = array_intersect_key($data, Tenant::SETTINGS_DEFAULTS);

        if ($settingsFields) {
            $companyFields['settings'] = array_merge($tenant->settings ?? [], $settingsFields);
        }

        $tenant->update($companyFields);

        activity('audit')
            ->performedOn($tenant)
            ->tap(fn ($a) => $a->tenant_id = $tenant->id)
            ->withProperties(['changed' => array_keys($companyFields)])
            ->log('Tenant settings updated');

        return $tenant->fresh();
    }

    public function uploadLogo(Tenant $tenant, UploadedFile $file): Tenant
    {
        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $file->store("tenants/{$tenant->id}", 'public');
        $tenant->update(['logo_path' => $path]);

        activity('audit')
            ->performedOn($tenant)
            ->tap(fn ($a) => $a->tenant_id = $tenant->id)
            ->log('Tenant logo updated');

        return $tenant->fresh();
    }
}
