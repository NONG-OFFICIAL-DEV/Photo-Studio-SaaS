<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PlatformSettingService
{
    public function update(array $data): PlatformSetting
    {
        $setting = PlatformSetting::current();

        $setting->update(array_intersect_key($data, array_flip([
            'bank_name', 'bank_account_name', 'bank_account_number', 'payment_instructions',
        ])));

        activity('audit')->performedOn($setting)->log('Platform payment settings updated');

        return $setting->fresh();
    }

    public function uploadKhqr(UploadedFile $file): PlatformSetting
    {
        $setting = PlatformSetting::current();

        if ($setting->khqr_image_path) {
            Storage::disk('public')->delete($setting->khqr_image_path);
        }

        $path = $file->store('platform', 'public');
        $setting->update(['khqr_image_path' => $path]);

        activity('audit')->performedOn($setting)->log('Platform KHQR image updated');

        return $setting->fresh();
    }
}
