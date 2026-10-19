<?php

namespace App\Models;

use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $status
 * @property int $created_by_user_id
 * @property int|null $received_by_user_id
 * @property Carbon|null $received_at
 * @property Carbon $created_at
 * @property-read Supplier $supplier
 * @property-read User $creator
 * @property-read User|null $receiver
 * @property-read Collection<int, PurchaseItem> $items
 */
#[Fillable(['supplier_id', 'status', 'created_by_user_id', 'received_by_user_id', 'received_at'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function reference(): string
    {
        return 'BUY-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function totalCost(): int
    {
        return (int) $this->items->sum('line_cost_idr');
    }
}
