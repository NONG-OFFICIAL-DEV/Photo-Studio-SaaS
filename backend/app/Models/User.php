<?php

namespace App\Models;

use App\Enums\PayType;
use App\Enums\UserStatus;
use App\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, HasUuids, MustVerifyEmail, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password', 'avatar_path',
        'locale', 'status', 'is_super_admin', 'last_login_at', 'last_login_ip',
        'pay_type', 'base_pay', 'commission_rate',
        'notification_channels', 'telegram_chat_id', 'telegram_link_token', 'telegram_linked_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /**
     * A channel with no explicit preference yet defaults to "on" for mail
     * and system (so a brand-new user isn't silently opted out of alerts
     * they never chose to mute), and "off" for telegram — that one can
     * never default to on regardless of preference, since it also requires
     * an actual linked chat_id (see wantsChannel()).
     */
    public const DEFAULT_NOTIFICATION_CHANNELS = ['mail' => true, 'system' => true, 'telegram' => false];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
            'pay_type' => PayType::class,
            'base_pay' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'status' => UserStatus::class,
            'notification_channels' => 'array',
            'telegram_linked_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function notificationChannelPreferences(): array
    {
        return array_merge(self::DEFAULT_NOTIFICATION_CHANNELS, $this->notification_channels ?? []);
    }

    public function hasTelegramLinked(): bool
    {
        return (bool) $this->telegram_chat_id;
    }

    public function wantsChannel(string $channel): bool
    {
        $wants = (bool) ($this->notificationChannelPreferences()[$channel] ?? false);

        return $channel === 'telegram' ? $wants && $this->hasTelegramLinked() : $wants;
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /* -----------------------------------------------------------------
     |  JWTSubject
     | ----------------------------------------------------------------- */

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Custom claims baked into the access token so the tenant and super
     * admin flag are available without an extra DB round trip.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'is_super_admin' => $this->is_super_admin,
        ];
    }
}
