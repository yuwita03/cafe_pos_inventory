<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuPriceRequest;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $filters = [
            'q' => trim($validated['q'] ?? ''),
            'category_id' => (int) ($validated['category_id'] ?? 0),
            'status' => $validated['status'] ?? 'all',
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
        $query = Product::with(['variants', 'optionGroups'])->orderBy('name')->orderBy('id');
        if ($filters['q'] !== '') {
            $query->whereLike('name', '%'.$filters['q'].'%');
        }
        if ($filters['category_id'] !== 0) {
            $query->where('category_id', $filters['category_id']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }
        $products = $query->paginate($filters['per_page'])->withQueryString()->through(fn (Product $product): array => [
            'id' => $product->id, 'name' => $product->name, 'description' => $product->description ?? '',
            'categoryId' => $product->category_id, 'active' => $product->is_active,
            'image' => $product->image_path ? route('admin.products.image', $product, false).'?v='.rawurlencode($product->image_path) : '',
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price_idr, 'available' => $variant->is_available,
            ]),
            'groups' => $product->optionGroups->map(fn (OptionGroup $group): array => [
                'id' => $group->id, 'required' => (bool) $group->pivot->getAttribute('is_required'),
            ]),
        ]);

        return Inertia::render('admin/menu/Index', [
            'section' => $request->route('section', 'products'),
            'filters' => $filters,
            'summary' => [
                'products' => Product::count(),
                'activeProducts' => Product::where('is_active', true)->count(),
            ],
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'prev_page_url' => $products->previousPageUrl(),
                'next_page_url' => $products->nextPageUrl(),
            ],
            'catalog' => [
                'categories' => Category::withCount('products')->orderBy('name')->get()->map(fn (Category $category): array => [
                    'id' => $category->id, 'name' => $category->name, 'active' => $category->is_active,
                    'productCount' => (int) $category->getAttribute('products_count'),
                ]),
                'groups' => OptionGroup::with('options')->withCount('products')->orderBy('name')->get()->map(fn (OptionGroup $group): array => [
                    'id' => $group->id, 'name' => $group->name, 'active' => $group->is_active, 'selection' => $group->selection_type,
                    'productCount' => (int) $group->getAttribute('products_count'),
                    'options' => $group->options->map(fn (Option $option): array => [
                        'id' => $option->id, 'name' => $option->name, 'price' => $option->price_delta_idr, 'active' => $option->is_active,
                    ]),
                ]),
                'products' => $products->items(),
            ],
        ]);
    }

    public function price(MenuPriceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $variant = ProductVariant::with(['product.category', 'product.optionGroups.options'])->whereKey($data['variant_id'])->firstOrFail();
        $product = $variant->product;
        if (! $variant->is_available || ! $product->is_active || ! $product->category->is_active) {
            throw ValidationException::withMessages(['variant_id' => 'Produk, kategori, atau varian tidak tersedia.']);
        }
        $selectedIds = $data['option_ids'];
        $allowedIds = [];
        $unitPrice = $variant->price_idr;
        foreach ($product->optionGroups as $group) {
            $required = (bool) $group->pivot->getAttribute('is_required');
            $availableOptions = $group->is_active ? $group->options->where('is_active', true) : collect();
            $ids = $availableOptions->pluck('id')->all();
            $allowedIds = array_merge($allowedIds, $ids);
            $selected = $availableOptions->whereIn('id', $selectedIds);
            if (($required && $selected->isEmpty()) || ($group->selection_type === 'single' && $selected->count() > 1)) {
                throw ValidationException::withMessages(['option_ids' => "Pilihan untuk {$group->name} tidak valid."]);
            }
            $unitPrice += $selected->sum('price_delta_idr');
        }
        if (array_diff($selectedIds, $allowedIds) !== []) {
            throw ValidationException::withMessages(['option_ids' => 'Opsi tidak aktif atau tidak ditugaskan ke produk ini.']);
        }
        $quantity = (int) $data['quantity'];
        if ($unitPrice > intdiv(9007199254740991, $quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Total harga melebihi batas yang didukung.']);
        }

        return response()->json(['unit_price_idr' => $unitPrice, 'quantity' => $quantity, 'total_idr' => $unitPrice * $quantity]);
    }
}
