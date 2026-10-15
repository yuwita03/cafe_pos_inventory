<?php

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $stock_item_id
 * @property string $quantity_delta
 * @property string $reason
 * @property string $source_key
 * @property int|null $actor_user_id
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
#[Fillable(['stock_item_id', 'quantity_delta', 'reason', 'source_key', 'actor_user_id'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (StockMovement $movement): never {
            throw new LogicException('Stock movements cannot be edited.');
        });
        static::deleting(function (StockMovement $movement): never {
            throw new LogicException('Stock movements cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:3'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
