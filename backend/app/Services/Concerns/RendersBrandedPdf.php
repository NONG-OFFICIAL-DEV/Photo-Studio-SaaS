<?php

namespace App\Services\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by any service that renders a tenant-branded document via
 * Browsershot (Invoice, Package quotes, ...) — embeds the tenant's logo
 * and the Khmer font as base64 data URIs rather than filesystem paths,
 * since Browsershot renders a bare HTML string with no base URL to
 * resolve a relative/local path against, and separately refuses to
 * render any HTML containing the literal substring "file:" as a raw
 * string-search safety check.
 */
trait RendersBrandedPdf
{
    protected function logoDataUri(Tenant $tenant): ?string
    {
        if (! $tenant->logo_path || ! Storage::disk('public')->exists($tenant->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($tenant->logo_path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($tenant->logo_path));
    }

    protected function fontDataUri(string $filename): string
    {
        return 'data:font/ttf;base64,'.base64_encode(file_get_contents(resource_path("fonts/{$filename}")));
    }
}
