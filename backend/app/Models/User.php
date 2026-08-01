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
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

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
        ];
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
