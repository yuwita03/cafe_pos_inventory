<?php

namespace App\Models;

use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $base_unit
 * @property string $on_hand_qty
 * @property string $reorder_point
 * @property bool $is_active
 */
#[Fillable(['name', 'base_unit', 'on_hand_qty', 'reorder_point', 'is_active'])]
class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['on_hand_qty' => 'decimal:3', 'reorder_point' => 'decimal:3', 'is_active' => 'boolean'];
    }

    /** @return HasMany<RecipeItem, $this> */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
