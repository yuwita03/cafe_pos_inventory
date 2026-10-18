<?php

namespace App\Http\Controllers;

use App\InventoryStock;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuestMenuController extends Controller
{
    public function index(Request $request, InventoryStock $inventory): Response|InertiaResponse
    {
        $validator = Validator::make(['table' => $request->query('table')], ['table' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);
        $table = $validator->fails() ? null : CafeTable::whereKey((int) $validator->validated()['table'])->where('is_active', true)->first();
        if (! $table) {
            return response('Meja tidak ditemukan atau sedang nonaktif. Silakan hubungi staf café.', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }

        $categories = Category::query()->where('is_active', true)->whereHas('products', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
        $groups = OptionGroup::query()->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('id')])->where('is_active', true)->get();
        $products = Product::query()->with(['variants.stockItem', 'variants.recipeItems.stockItem', 'optionGroups'])->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
        $reservedQuantities = $inventory->reservedQuantities();

        $screen = $request->route('screen', 'menu');
        $productId = $screen === 'product' ? (int) $request->route('product') : null;
        if ($screen === 'product') {
            abort_unless($products->contains('id', $productId), 404);
        }

        return Inertia::render('guest/Menu', [
            'screen' => $screen,
            'productId' => $productId,
            'cafeName' => config('app.name'),
            'table' => ['id' => $table->id, 'label' => $table->label],
            'catalog' => [
                'categories' => $categories->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name]),
                'groups' => $groups->map(fn (OptionGroup $group): array => [
                    'id' => $group->id, 'name' => $group->name, 'selection' => $group->selection_type,
                    'options' => $group->options->map(fn (Option $option): array => ['id' => $option->id, 'name' => $option->name, 'price' => $option->price_delta_idr])->values(),
                ]),
                'products' => $products->map(fn (Product $product): array => [
                    'id' => $product->id, 'name' => $product->name, 'description' => $product->description ?? '',
                    'categoryId' => $product->category_id,
                    'image' => $product->image_path ? route('guest.menu.product-image', $product, false) : '',
                    'variants' => $product->variants->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price_idr, 'available' => $variant->isOrderable($reservedQuantities)]),
                    'groups' => $product->optionGroups->filter(fn (OptionGroup $group) => $group->is_active && $group->options->isNotEmpty())
                        ->map(fn (OptionGroup $group): array => ['id' => $group->id, 'required' => (bool) $group->pivot->getAttribute('is_required')])->values(),
                ]),
            ],
        ]);
    }

    public function productImage(Product $product): BinaryFileResponse
    {
        abort_unless($product->is_active && $product->image_path && Storage::disk('public')->exists($product->image_path), 404);

        return response()->file(Storage::disk('public')->path($product->image_path), [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
