<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'email', 'phone', 'address',
        'logo_path', 'timezone', 'currency', 'locale', 'is_active', 'settings',
    ];

    /**
     * Defaults for the free-form `settings` JSONB column's known keys —
     * invoicing/theme preferences configurable from Settings > Invoicing
     * and Settings > Theme. Anything not explicitly set by a tenant reads
     * as these defaults rather than null.
     */
    public const SETTINGS_DEFAULTS = [
        'invoice_prefix' => 'INV-',
        'default_tax_rate' => 0,
        'default_due_days' => 14,
        'invoice_footer' => null,
        'primary_color' => null,
        'secondary_color' => null,
        'attendance_expected_start_time' => '09:00',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The subscription currently in effect (latest by period start).
     *
     * Deliberately a plain ->latest() + single-row HasOne rather than
     * ->latestOfMany(), which aggregates MAX(subscriptions.id) as a
     * tie-breaker — Postgres has no MAX() for uuid columns. latestOfMany
     * is built for efficiently eager-loading across many parents at once;
     * this relation is only ever accessed for one tenant at a time, so the
     * simpler form is both correct and sufficient.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest('current_period_start');
    }

    public function settingsWithDefaults(): array
    {
        return array_merge(self::SETTINGS_DEFAULTS, $this->settings ?? []);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settingsWithDefaults()[$key] ?? $default;
    }
}
