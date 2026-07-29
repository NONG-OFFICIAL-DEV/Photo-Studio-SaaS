<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['tenant_id', 'inventory_item_id', 'type', 'quantity', 'reason', 'moved_at', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:2',
            'moved_at' => 'date',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
