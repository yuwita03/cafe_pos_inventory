<?php

namespace App\Http\Controllers\Admin;

use App\CashOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdvanceKitchenOrderRequest;
use App\Http\Requests\Admin\CashCheckoutRequest;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashOrderController extends Controller
{
    public function pos(Request $request): Response
    {
        abort_unless(in_array($request->user()?->role, ['owner', 'cashier'], true), 403);

        return Inertia::render('admin/pos/Index', [
            'catalog' => [
                'categories' => Category::where('is_active', true)->orderBy('name')->get()->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'active' => true]),
                'groups' => OptionGroup::with('options')->where('is_active', true)->get()->map(fn (OptionGroup $group): array => [
                    'id' => $group->id, 'name' => $group->name, 'selection' => $group->selection_type,
                    'options' => $group->options->where('is_active', true)->map(fn (Option $option): array => ['id' => $option->id, 'name' => $option->name, 'price' => $option->price_delta_idr])->values(),
                ]),
                'products' => Product::with(['variants', 'optionGroups'])->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('name')->get()->map(fn (Product $product): array => [
                    'id' => $product->id, 'name' => $product->name, 'description' => $product->description ?? '', 'categoryId' => $product->category_id, 'active' => true,
                    'image' => $product->image_path ? route('admin.products.image', $product, false).'?v='.rawurlencode($product->image_path) : '',
                    'variants' => $product->variants->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price_idr, 'available' => $variant->is_available]),
                    'groups' => $product->optionGroups->map(fn (OptionGroup $group): array => ['id' => $group->id, 'required' => (bool) $group->pivot->getAttribute('is_required')]),
                ]),
            ],
            'tables' => CafeTable::where('is_active', true)->orderBy('label')->get()->map(fn (CafeTable $table): array => ['id' => $table->id, 'label' => $table->label]),
        ]);
    }

    public function checkout(CashCheckoutRequest $request, CashOrders $cash): JsonResponse
    {
        try {
            $order = $cash->checkout($request->user(), $request->validated());
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['checkout_key' => 'Kunci checkout sudah digunakan. Periksa daftar pesanan sebelum mencoba lagi.']);
            }
            throw $exception;
        }

        return response()->json(['order' => $cash->present(collect([$order]))[0]]);
    }

    public function orders(Request $request, CashOrders $cash): Response
    {
        abort_unless(in_array($request->user()?->role, ['owner', 'cashier'], true), 403);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['awaiting_payment', 'queued', 'preparing', 'ready', 'completed', 'cancelled', 'payment_review'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'expired', 'refunded'])],
            'source' => ['nullable', Rule::in(['cashier', 'table_qr'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'order' => ['nullable', 'string', 'regex:/^ORD-\d{1,20}$/'],
        ]);
        $scope = Order::query();
        if ($request->user()->role === 'cashier') {
            $scope->where('created_by_user_id', $request->user()->id);
        }
        $selected = isset($data['order']) ? (clone $scope)->findOrFail((int) substr($data['order'], 4)) : null;
        $filters = ['q' => trim($data['q'] ?? ''), 'status' => $data['status'] ?? '', 'payment_status' => $data['payment_status'] ?? '', 'source' => $data['source'] ?? ''];
        $query = clone $scope;
        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($query) use ($search): void {
                $query->whereLike('customer_name', '%'.$search.'%')->orWhereRaw("('ORD-' || LPAD(id::text, GREATEST(6, LENGTH(id::text)), '0')) ILIKE ?", ['%'.$search.'%'])
                    ->orWhereIn('table_id', CafeTable::whereLike('label', '%'.$search.'%')->select('id'));
            });
        }
        foreach (['status', 'source'] as $key) {
            if ($filters[$key] !== '') {
                $query->where($key, $filters[$key]);
            }
        }
        if ($filters['payment_status'] !== '') {
            $query->whereIn('id', DB::table('payments')->where('status', $filters['payment_status'])->select('order_id'));
        }
        $summary = ['total' => (clone $query)->count(), 'pending' => (clone $query)->whereIn('id', DB::table('payments')->where('status', 'pending')->select('order_id'))->count(), 'review' => (clone $query)->where('status', 'payment_review')->count()];
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return Inertia::render('admin/orders/Index', [
            'orders' => $cash->present($page->getCollection()), 'filters' => $filters, 'summary' => $summary,
            'selectedOrder' => $selected ? $cash->present(collect([$selected]))[0] : null,
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ]);
    }

    public function kitchen(Request $request, CashOrders $cash): Response
    {
        abort_unless(in_array($request->user()?->role, ['owner', 'kitchen'], true), 403);
        $paid = Order::whereIn('id', DB::table('payments')->where('status', 'paid')->whereColumn('amount_idr', 'orders.total_idr')->select('order_id'));
        $active = (clone $paid)->whereIn('status', ['queued', 'preparing', 'ready'])->orderBy('created_at')->orderBy('id')->get();
        $completed = (clone $paid)->where('status', 'completed')->orderByDesc('updated_at')->orderByDesc('id')->limit(50)->get();

        return Inertia::render('admin/kitchen/Index', ['orders' => $cash->present($active->concat($completed))]);
    }

    public function advance(AdvanceKitchenOrderRequest $request, Order $order, CashOrders $cash): RedirectResponse
    {
        $cash->advance($order->id, $request->validated('expected_status'), $request->user());

        return back()->with('success', 'Status pesanan diperbarui.');
    }
}
