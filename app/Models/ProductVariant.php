<?php

namespace App\Models;

use App\StockQuantity;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $price_idr
 * @property bool $is_available
 * @property int|null $stock_item_id
 * @property-read StockItem|null $stockItem
 * @property-read Collection<int, RecipeItem> $recipeItems
 * @property-read Product $product
 */
#[Fillable(['product_id', 'name', 'price_idr', 'is_available', 'stock_item_id'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price_idr' => 'integer', 'is_available' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<RecipeItem, $this> */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function hasValidStockConfiguration(): bool
    {
        if ($this->stock_item_id !== null) {
            return $this->stockItem !== null && $this->stockItem->is_active && $this->stockItem->base_unit === 'pcs';
        }

        return $this->recipeItems->isNotEmpty() && $this->recipeItems->every(fn (RecipeItem $row): bool => $row->stockItem->is_active
            && in_array($row->stockItem->base_unit, ['g', 'ml'], true)
            && StockQuantity::milli($row->quantity) > 0
        );
    }

    /** @param array<int, int> $reservedQuantities */
    public function isOrderable(array $reservedQuantities): bool
    {
        if (! $this->is_available || ! $this->hasValidStockConfiguration()) {
            return false;
        }
        if ($this->stock_item_id !== null && $this->stockItem !== null) {
            return StockQuantity::milli($this->stockItem->on_hand_qty) - ($reservedQuantities[$this->stock_item_id] ?? 0) >= 1000;
        }

        return $this->recipeItems->every(fn (RecipeItem $row): bool => StockQuantity::milli($row->stockItem->on_hand_qty) - ($reservedQuantities[$row->stock_item_id] ?? 0) >= StockQuantity::milli($row->quantity)
        );
    }
}
