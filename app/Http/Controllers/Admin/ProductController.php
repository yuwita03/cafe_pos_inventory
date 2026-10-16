<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Product;
use App\Models\StockItem;
use App\ProductImage;
use App\ProductStockConfiguration;
use App\StockQuantity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private ProductImage $images) {}

    public function store(SaveProductRequest $request): RedirectResponse
    {
        return $this->save($request, new Product);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        return $this->save($request, $product);
    }

    public function status(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $product->update($data);

        return back()->with('success', 'Status produk berhasil diperbarui.');
    }

    public function image(Product $product): BinaryFileResponse
    {
        abort_unless($product->image_path && Storage::disk('public')->exists($product->image_path), 404);

        return response()->file(Storage::disk('public')->path($product->image_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function save(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $oldImage = $product->image_path;
        $upload = $request->file('image');
        $newImage = $upload ? $this->images->store($upload) : null;
        try {
            DB::transaction(function () use ($data, $product, $newImage, $request): void {
                if ($product->exists) {
                    $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                    $product->setRelation('variants', $product->variants()->orderBy('id')->lockForUpdate()->get());
                    $submittedIds = array_filter(array_column($data['variants'], 'id'));
                    $product->variants()->whereNotIn('id', $submittedIds)->delete();
                    foreach ($product->variants as $variant) {
                        $variant->update(['name' => '__editing_'.Str::uuid()]);
                    }
                }
                $stockIds = $product->exists ? $product->variants->pluck('stock_item_id')->filter()->all() : [];
                $existingStocks = $product->exists ? $product->variants->pluck('stock_item_id', 'id')->all() : [];
                $thresholds = [];
                foreach ($data['variants'] as $variantData) {
                    $stock = $variantData['stock'] ?? [];
                    $stockIds = array_merge($stockIds, array_column($stock['ingredients'] ?? [], 'stock_item_id'));
                    $stockId = $existingStocks[(int) ($variantData['id'] ?? 0)] ?? null;
                    if (($stock['kind'] ?? null) === 'finished' && $stockId && isset($stock['reorder_point'])) {
                        $threshold = StockQuantity::milli($stock['reorder_point']);
                        if (isset($thresholds[$stockId]) && $thresholds[$stockId] !== $threshold) {
                            throw ValidationException::withMessages(['variants' => 'Varian yang memakai stok sama harus memiliki batas stok rendah yang sama.']);
                        }
                        $thresholds[$stockId] = $threshold;
                    }
                }
                StockItem::whereIn('id', $stockIds)->orderBy('id')->lockForUpdate()->get();
                $product->fill(Arr::only($data, ['category_id', 'name', 'description', 'is_active']));
                if ($newImage) {
                    $product->image_path = $newImage;
                } elseif ($data['remove_image'] ?? false) {
                    $product->image_path = null;
                }
                $product->save();
                foreach ($data['variants'] as $variantData) {
                    $isNewVariant = empty($variantData['id']);
                    $attributes = Arr::only($variantData, ['name', 'price_idr', 'is_available']);
                    if (! $isNewVariant) {
                        $variant = $product->variants()->whereKey($variantData['id'])->firstOrFail();
                        $variant->update($attributes);
                    } else {
                        $variant = $product->variants()->create($attributes);
                    }
                    if (isset($variantData['stock'])) {
                        $variant->setRelation('product', $product);
                        app(ProductStockConfiguration::class)->save($variant, $variantData['stock'], $isNewVariant, $request->user()?->id);
                    }
                }
                $assignments = [];
                foreach ($data['groups'] as $group) {
                    $assignments[$group['id']] = ['is_required' => $group['is_required']];
                }
                $product->optionGroups()->sync($assignments);
            });
        } catch (Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }
            if ($exception instanceof QueryException && (
                $exception->getCode() === '23503' ||
                ($exception->getCode() === '23000' && str_contains($exception->errorInfo[2] ?? '', 'FOREIGN KEY constraint failed'))
            )) {
                throw ValidationException::withMessages(['variants' => 'Varian masih digunakan oleh data lain dan tidak dapat dihapus.']);
            }
            if ($exception instanceof QueryException && $exception->getCode() === '23505') {
                throw ValidationException::withMessages(['variants' => 'Nama stok atau varian sudah digunakan. Periksa kembali nama produk dan varian.']);
            }
            throw $exception;
        }
        if ($oldImage && ($newImage || ($data['remove_image'] ?? false))) {
            Storage::disk('public')->delete($oldImage);
        }

        return back()->with('success', 'Produk berhasil disimpan.');
    }
}
