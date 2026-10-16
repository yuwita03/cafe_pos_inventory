<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\InventoryRequirementsRequest;
use App\Http\Requests\Admin\SaveOptionIngredientsRequest;
use App\Http\Requests\Admin\SaveStockItemRequest;
use App\Http\Requests\Admin\SaveVariantRecipeRequest;
use App\Http\Requests\Admin\SetStockQuickAddRequest;
use App\InventoryRequirements;
use App\InventoryStock;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\OptionIngredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\ProductStockConfiguration;
use App\StockQuantity;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $reservations = DB::table('stock_reservations')->where('status', 'active')->groupBy('stock_item_id')->selectRaw('stock_item_id, SUM(quantity) AS quantity')->pluck('quantity', 'stock_item_id');
        $variants = ProductVariant::with('recipeItems')->get();
        $recipes = [];
        foreach ($variants as $variant) {
            if ($variant->stock_item_id !== null || $variant->recipeItems->isNotEmpty()) {
                $recipes[$variant->id] = ['kind' => $variant->stock_item_id !== null ? 'finished' : 'ingredients', 'stockId' => $variant->stock_item_id ?? 0,
                    'ingredients' => $variant->recipeItems->map(fn (RecipeItem $row): array => ['stockId' => $row->stock_item_id, 'quantity' => (float) $row->quantity])->all()];
            }
        }

        return Inertia::render('admin/inventory/Index', [
            'section' => $request->route('section', 'stock'),
            'stockItems' => StockItem::orderBy('name')->orderBy('id')->get()->map(fn (StockItem $item): array => [
                'id' => $item->id, 'name' => $item->name, 'unit' => $item->base_unit,
                'onHand' => (float) $item->on_hand_qty, 'reserved' => (float) ($reservations[$item->id] ?? 0), 'available' => (StockQuantity::milli($item->on_hand_qty) - StockQuantity::milli($reservations[$item->id] ?? '0')) / 1000,
                'threshold' => (float) $item->reorder_point, 'active' => $item->is_active, 'quickAdd' => $item->is_quick_add,
                'lowStock' => $item->is_active && StockQuantity::milli($item->on_hand_qty) - StockQuantity::milli($reservations[$item->id] ?? '0') <= StockQuantity::milli($item->reorder_point),
            ]),
            'stockMovements' => StockMovement::with('actor')->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn (StockMovement $movement): array => [
                'id' => $movement->id, 'stockId' => $movement->stock_item_id, 'delta' => (float) $movement->quantity_delta,
                'reason' => $movement->reason, 'source' => str_starts_with($movement->reason, 'Order ORD-') ? 'Penjualan' : 'Adjustment', 'actor' => $movement->actor->name ?? 'Tanpa akun (lokal)',
                'time' => $movement->created_at->timezone(config('app.timezone'))->format('d M Y H:i T'),
            ]),
            'variantRecipes' => $recipes,
            'optionIngredients' => Option::with('ingredients')->get()->mapWithKeys(fn (Option $option): array => [$option->id => $option->ingredients->map(fn (OptionIngredient $row): array => ['stockId' => $row->stock_item_id, 'quantity' => (float) $row->additional_qty])->all()]),
            'recipeCatalog' => [
                'products' => Product::with(['variants', 'optionGroups'])->orderBy('name')->orderBy('id')->get()->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'active' => $product->is_active,
                    'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                        'id' => $variant->id, 'name' => $variant->name, 'available' => $variant->is_available,
                    ]),
                    'groups' => $product->optionGroups->map(fn (OptionGroup $group): array => [
                        'id' => $group->id, 'required' => (bool) $group->pivot->getAttribute('is_required'),
                    ]),
                ]),
                'groups' => OptionGroup::with('options')->orderBy('name')->get()->map(fn (OptionGroup $group): array => [
                    'id' => $group->id, 'name' => $group->name,
                    'active' => $group->is_active, 'selection' => $group->selection_type,
                    'options' => $group->options->map(fn (Option $option): array => [
                        'id' => $option->id, 'name' => $option->name, 'active' => $option->is_active,
                    ]),
                ]),
            ],
        ]);
    }

    public function store(SaveStockItemRequest $request): RedirectResponse
    {
        StockItem::create($request->validated());

        return back()->with('success', 'Item stok berhasil ditambahkan dengan saldo nol.');
    }

    public function update(SaveStockItemRequest $request, StockItem $stockItem): RedirectResponse
    {
        DB::transaction(function () use ($request, $stockItem): void {
            $item = StockItem::query()->lockForUpdate()->findOrFail($stockItem->id);
            $data = $request->validated();
            if ($data['base_unit'] !== $item->base_unit) {
                throw ValidationException::withMessages(['base_unit' => 'Unit dasar item yang sudah dibuat tidak dapat diubah.']);
            }
            if (! $data['is_active'] && DB::table('stock_reservations')->where('stock_item_id', $item->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Item masih direservasi pesanan aktif dan belum dapat dinonaktifkan.']);
            }
            $item->update($data);
        });

        return back()->with('success', 'Item stok berhasil diperbarui.');
    }

    public function quickAdd(SetStockQuickAddRequest $request, StockItem $stockItem): RedirectResponse
    {
        DB::transaction(function () use ($request, $stockItem): void {
            $item = StockItem::query()->lockForUpdate()->findOrFail($stockItem->id);
            $enabled = $request->boolean('is_quick_add');
            if ($enabled && $item->base_unit === 'pcs') {
                throw ValidationException::withMessages(['is_quick_add' => 'Tambah cepat bahan hanya tersedia untuk item dengan unit g atau ml.']);
            }
            $item->update(['is_quick_add' => $enabled]);
        });

        return back()->with('success', 'Preferensi tambah cepat disimpan.');
    }

    public function adjust(AdjustStockRequest $request, InventoryStock $stock): RedirectResponse
    {
        $data = $request->validated();
        try {
            $stock->adjust((int) $data['stock_item_id'], (string) $data['quantity_delta'], $data['reason'], $data['source_key'], $request->user()?->id);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['source_key' => 'Kunci adjustment sudah digunakan. Muat ulang data sebelum membuat perubahan baru.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Adjustment stok tercatat.');
    }

    public function saveRecipe(SaveVariantRecipeRequest $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $variant, $request): void {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            app(ProductStockConfiguration::class)->save($variant, ['kind' => 'ingredients', 'ingredients' => $data['ingredients']], false, $request->user()?->id);
        }, 3);

        return back()->with('success', 'Konfigurasi stok varian berhasil disimpan.');
    }

    public function saveOption(SaveOptionIngredientsRequest $request, Option $option): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $option): void {
            $option = Option::query()->lockForUpdate()->findOrFail($option->id);
            $this->lockIngredients(array_column($data['ingredients'], 'stock_item_id'), ['g', 'ml']);
            $option->ingredients()->delete();
            foreach ($data['ingredients'] as $row) {
                $option->ingredients()->create(['stock_item_id' => $row['stock_item_id'], 'additional_qty' => StockQuantity::decimal(StockQuantity::milli($row['quantity']))]);
            }
        }, 3);

        return back()->with('success', 'Tambahan bahan opsi berhasil disimpan.');
    }

    public function requirements(InventoryRequirementsRequest $request, InventoryRequirements $requirements): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['items' => $requirements->calculate((int) $data['variant_id'], array_values(array_map('intval', $data['option_ids'])), (int) $data['quantity'])]);
    }

    /** @param list<int> $ids
     * @param  list<string>  $units
     */
    private function lockIngredients(array $ids, array $units): void
    {
        $items = StockItem::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($items->count() !== count($ids) || $items->contains(fn (StockItem $item): bool => ! $item->is_active || ! in_array($item->base_unit, $units, true))) {
            throw ValidationException::withMessages(['ingredients' => 'Pilih stock item aktif dengan unit yang sesuai.']);
        }
    }
}
