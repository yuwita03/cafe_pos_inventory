<?php

namespace App\Models;

use Database\Factories\RecipeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $stock_item_id
 * @property string $quantity
 * @property-read StockItem $stockItem
 */
#[Fillable(['product_variant_id', 'stock_item_id', 'quantity'])]
class RecipeItem extends Model
{
    /** @use HasFactory<RecipeItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
