<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Exactly one row, ever — see the migration's docblock for why this is a
 * singleton rather than a per-tenant or key/value table. current() matches
 * on no attributes at all, so it fetches whichever single row already
 * exists, or creates the first (and only) one.
 */
class PlatformSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'khqr_image_path', 'bank_name', 'bank_account_name', 'bank_account_number', 'payment_instructions',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
