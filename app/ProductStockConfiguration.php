<?php

namespace App;

use App\Models\ProductVariant;
use App\Models\StockItem;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductStockConfiguration
{
    /**
     * @param  array{kind: 'ingredients'|'finished', stock_item_id?: int|numeric-string|null, opening_qty?: int|numeric-string, reorder_point?: numeric-string|int|float, ingredients?: list<array{stock_item_id: int|numeric-string, quantity: numeric-string|int|float}>}  $configuration
     */
    public function save(ProductVariant $variant, array $configuration, bool $isNewVariant, ?int $actorId): void
    {
        $hasRecipe = $variant->stock_item_id === null && $variant->recipeItems()->exists();
        if (($variant->stock_item_id !== null && $configuration['kind'] !== 'finished') || ($hasRecipe && $configuration['kind'] !== 'ingredients')) {
            throw ValidationException::withMessages(['variants' => 'Cara pengurangan stok yang sudah diatur tidak dapat diubah.']);
        }
        if (array_key_exists('stock_item_id', $configuration)) {
            $submittedStockId = isset($configuration['stock_item_id']) ? (int) $configuration['stock_item_id'] : null;
            if ($submittedStockId !== $variant->stock_item_id) {
                throw ValidationException::withMessages(['variants' => 'Stok produk terhubung otomatis dan tidak dapat diganti atau dilepas.']);
            }
        }
        if ($configuration['kind'] === 'ingredients' && empty($configuration['ingredients'])) {
            throw ValidationException::withMessages(['variants' => 'Tambahkan minimal satu bahan untuk varian ini.']);
        }
        if (! $isNewVariant && array_key_exists('opening_qty', $configuration)) {
            throw ValidationException::withMessages(['variants' => 'Stok awal hanya dapat diisi untuk varian baru. Gunakan Adjustment untuk mengubah saldo.']);
        }
        if ($configuration['kind'] === 'ingredients') {
            $rows = collect($configuration['ingredients'])->sortBy('stock_item_id')->values();
            $ids = $rows->pluck('stock_item_id')->map(fn ($id): int => (int) $id)->all();
            $items = StockItem::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $normalized = $rows->map(fn (array $row): array => ['stock_item_id' => (int) $row['stock_item_id'], 'quantity' => StockQuantity::decimal(StockQuantity::milli($row['quantity']))])->all();
            $current = $variant->recipeItems()->orderBy('stock_item_id')->get()->map(fn ($row): array => ['stock_item_id' => $row->stock_item_id, 'quantity' => $row->quantity])->all();
            if ($items->count() !== count($ids) || $items->contains(fn (StockItem $item): bool => ! $item->is_active || ! in_array($item->base_unit, ['g', 'ml'], true))) {
                throw ValidationException::withMessages(['variants' => 'Pilih bahan aktif dengan unit g atau ml.']);
            }
            if ($current === $normalized) {
                return;
            }
            $variant->update(['stock_item_id' => null]);
            $variant->recipeItems()->delete();
            $variant->recipeItems()->createMany($normalized);

            return;
        }

        $stockId = $variant->stock_item_id;
        if ($stockId) {
            $stock = StockItem::query()->lockForUpdate()->findOrFail($stockId);
            if ($stock->base_unit !== 'pcs') {
                throw ValidationException::withMessages(['variants' => 'Stok produk jadi harus menggunakan unit pcs.']);
            }
            $stock->update(['reorder_point' => $configuration['reorder_point'] ?? $stock->reorder_point]);
        } else {
            $name = $variant->product->name.($variant->name === 'Regular' ? '' : ' · '.$variant->name);
            $name = Str::limit($name, 120, '');
            if (StockItem::where('name', $name)->exists()) {
                $suffix = ' · '.$variant->id;
                $name = Str::limit($name, 120 - mb_strlen($suffix), '').$suffix;
            }
            $stock = StockItem::create(['name' => $name, 'base_unit' => 'pcs', 'reorder_point' => $configuration['reorder_point'] ?? 0, 'is_active' => true]);
            $opening = (int) ($configuration['opening_qty'] ?? 0);
            if ($opening > 0) {
                app(InventoryStock::class)->adjust($stock->id, (string) $opening, 'Stok awal', (string) Str::uuid(), $actorId);
            }
        }
        $variant->recipeItems()->delete();
        $variant->update(['stock_item_id' => $stock->id]);
    }
}
