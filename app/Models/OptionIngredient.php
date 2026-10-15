<?php

namespace App\Models;

use Database\Factories\OptionIngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $stock_item_id
 * @property string $additional_qty
 * @property-read StockItem $stockItem
 */
#[Fillable(['option_id', 'stock_item_id', 'additional_qty'])]
class OptionIngredient extends Model
{
    /** @use HasFactory<OptionIngredientFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['additional_qty' => 'decimal:3'];
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
