<?php

namespace App;

use App\Models\ProductVariant;
use App\Models\StockItem;
use Illuminate\Validation\ValidationException;

final class InventoryRequirements
{
    /**
     * @param  list<int>  $optionIds
     * @return list<array{stockId: int, name: string, unit: string, base: string, extra: string, total: string}>
     */
    public function calculate(int $variantId, array $optionIds, int $quantity): array
    {
        $variant = ProductVariant::with(['product.category', 'product.optionGroups.options.ingredients.stockItem', 'recipeItems.stockItem', 'stockItem'])->findOrFail($variantId);
        if (! $variant->is_available || ! $variant->product->is_active || ! $variant->product->category->is_active) {
            throw ValidationException::withMessages(['variant_id' => 'Produk, kategori, atau varian tidak tersedia.']);
        }
        $allowedIds = [];
        $selectedIngredients = [];
        foreach ($variant->product->optionGroups as $group) {
            $options = $group->is_active ? $group->options->where('is_active', true) : collect();
            $allowedIds = array_merge($allowedIds, $options->pluck('id')->all());
            $selected = $options->whereIn('id', $optionIds);
            if (((bool) $group->pivot->getAttribute('is_required') && $selected->isEmpty()) || ($group->selection_type === 'single' && $selected->count() > 1)) {
                throw ValidationException::withMessages(['option_ids' => "Pilihan untuk {$group->name} tidak valid."]);
            }
            foreach ($selected as $option) {
                foreach ($option->ingredients as $ingredient) {
                    $selectedIngredients[] = $ingredient;
                }
            }
        }
        if (array_diff($optionIds, $allowedIds) !== []) {
            throw ValidationException::withMessages(['option_ids' => 'Opsi tidak aktif atau tidak ditugaskan ke produk ini.']);
        }
        /** @var array<int, array{item: StockItem, base: int, extra: int}> $totals */
        $totals = [];
        $add = function (StockItem $item, int $milli, string $source) use (&$totals): void {
            if (! $item->is_active) {
                throw ValidationException::withMessages(['variant_id' => "Bahan {$item->name} sedang nonaktif."]);
            }
            $totals[$item->id] ??= ['item' => $item, 'base' => 0, 'extra' => 0];
            $totals[$item->id][$source] += $milli;
        };
        if ($variant->stock_item_id !== null) {
            if (! $variant->stockItem || $variant->stockItem->base_unit !== 'pcs') {
                throw ValidationException::withMessages(['variant_id' => 'Stok jadi varian harus menggunakan unit pcs.']);
            }
            $add($variant->stockItem, 1000, 'base');
        } else {
            if ($variant->recipeItems->isEmpty()) {
                throw ValidationException::withMessages(['variant_id' => 'Varian ini belum memiliki resep.']);
            }
            foreach ($variant->recipeItems as $ingredient) {
                $add($ingredient->stockItem, StockQuantity::milli($ingredient->quantity), 'base');
            }
        }
        foreach ($selectedIngredients as $ingredient) {
            $add($ingredient->stockItem, StockQuantity::milli($ingredient->additional_qty), 'extra');
        }
        ksort($totals);
        $requirements = [];
        foreach ($totals as $id => $total) {
            $amount = ($total['base'] + $total['extra']) * $quantity;
            if ($amount > StockQuantity::MAX_MILLI) {
                throw ValidationException::withMessages(['quantity' => 'Kebutuhan stok melebihi batas yang didukung.']);
            }
            $requirements[] = ['stockId' => $id, 'name' => $total['item']->name, 'unit' => $total['item']->base_unit,
                'base' => StockQuantity::decimal($total['base']), 'extra' => StockQuantity::decimal($total['extra']), 'total' => StockQuantity::decimal($amount)];
        }

        return $requirements;
    }
}
