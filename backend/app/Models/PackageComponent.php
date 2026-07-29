<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageComponent extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['tenant_id', 'package_id', 'service_id', 'addon_id', 'quantity', 'is_optional'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_optional' => 'boolean',
        ];
    }

    protected $attributes = ['quantity' => 1, 'is_optional' => false];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddOn::class, 'addon_id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->service?->name ?? $this->addon?->name;
    }

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->service?->price ?? $this->addon?->price ?? 0);
    }

    public function getLineTotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 2);
    }
}
