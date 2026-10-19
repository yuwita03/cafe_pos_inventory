<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePurchaseRequest;
use App\InventoryStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use App\Models\Supplier;
use App\StockQuantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/purchases/Index', [
            'purchases' => Purchase::with(['supplier', 'items'])->latest('id')->get()->map(fn (Purchase $purchase): array => [
                'id' => $purchase->id, 'reference' => $purchase->reference(), 'supplier' => $purchase->supplier->name,
                'date' => $purchase->created_at->timezone(config('app.timezone'))->format('d M Y H:i'),
                'status' => $purchase->status, 'total' => $purchase->totalCost(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchases/Create', $this->editorProps());
    }

    public function store(SavePurchaseRequest $request): RedirectResponse
    {
        $purchase = DB::transaction(function () use ($request): Purchase {
            $purchase = new Purchase(['created_by_user_id' => $request->user()->id, 'status' => 'draft']);
            $this->saveDraft($purchase, $request->purchaseData());

            return $purchase;
        }, 3);

        return to_route('admin.purchases.show', $purchase);
    }

    public function show(Purchase $purchase): Response
    {
        return Inertia::render('admin/purchases/Show', ['purchase' => $this->purchaseData($purchase)]);
    }

    public function edit(Purchase $purchase): Response
    {
        $this->ensureDraft($purchase);

        return Inertia::render('admin/purchases/Edit', [
            ...$this->editorProps($purchase), 'purchase' => $this->purchaseData($purchase),
        ]);
    }

    public function update(SavePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchase): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->ensureDraft($locked);
            $this->saveDraft($locked, $request->purchaseData());
        }, 3);

        return to_route('admin.purchases.show', $purchase);
    }

    public function receive(Request $request, Purchase $purchase, InventoryStock $stock): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchase, $stock): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if ($locked->status === 'received') {
                return;
            }
            $this->ensureDraft($locked);
            $lines = $locked->items()->orderBy('stock_item_id')->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'Pembelian harus memiliki minimal satu item.']);
            }
            StockItem::whereIn('id', $lines->pluck('stock_item_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($lines as $line) {
                $stock->adjust($line->stock_item_id, $line->quantity, 'Pembelian '.$locked->reference().' diterima', (string) Str::uuid(), $request->user()->id);
            }
            $locked->update(['status' => 'received', 'received_by_user_id' => $request->user()->id, 'received_at' => now()]);
        }, 3);

        return to_route('admin.purchases.show', $purchase);
    }

    public function cancel(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->ensureDraft($locked);
            $locked->update(['status' => 'cancelled']);
        }, 3);

        return to_route('admin.purchases.show', $purchase);
    }

    private function ensureDraft(Purchase $purchase): void
    {
        if ($purchase->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft pembelian yang dapat diubah, diterima, atau dibatalkan.']);
        }
    }

    /** @param array{supplier_id: int, items: list<array{stock_item_id: int, quantity: string, line_cost_idr: int}>} $data */
    private function saveDraft(Purchase $purchase, array $data): void
    {
        $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
        if (! $supplier->is_active && $purchase->supplier_id !== $supplier->id) {
            throw ValidationException::withMessages(['supplier_id' => 'Pilih supplier aktif untuk pembelian baru.']);
        }
        $existing = $purchase->exists ? $purchase->items()->pluck('stock_item_id')->all() : [];
        $stocks = StockItem::whereIn('id', array_column($data['items'], 'stock_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($data['items'] as $index => $line) {
            if (! $stocks->get($line['stock_item_id'])?->is_active && ! in_array((int) $line['stock_item_id'], $existing, true)) {
                throw ValidationException::withMessages(["items.$index.stock_item_id" => 'Pilih item stok aktif.']);
            }
        }
        $purchase->supplier_id = $supplier->id;
        $purchase->save();
        $purchase->items()->delete();
        foreach ($data['items'] as $line) {
            $purchase->items()->create([
                'stock_item_id' => $line['stock_item_id'], 'quantity' => StockQuantity::decimal(StockQuantity::milli($line['quantity'])), 'line_cost_idr' => $line['line_cost_idr'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function editorProps(?Purchase $purchase = null): array
    {
        return [
            'suppliers' => Supplier::where(fn ($query) => $query->where('is_active', true)->when($purchase, fn ($query) => $query->orWhere('id', $purchase->supplier_id)))
                ->orderBy('name')->get()->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name, 'is_active' => $supplier->is_active]),
            'stockItems' => StockItem::where(fn ($query) => $query->where('is_active', true)->when($purchase, fn ($query) => $query->orWhereIn('id', $purchase->items()->select('stock_item_id'))))
                ->orderBy('name')->get()->map(fn (StockItem $item): array => ['id' => $item->id, 'name' => $item->name, 'unit' => $item->base_unit, 'active' => $item->is_active]),
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseData(Purchase $purchase): array
    {
        $purchase->load(['supplier', 'creator', 'receiver', 'items.stockItem']);

        return [
            'id' => $purchase->id, 'reference' => $purchase->reference(), 'status' => $purchase->status,
            'supplier_id' => $purchase->supplier_id, 'supplier' => $purchase->supplier->name,
            'supplierActive' => $purchase->supplier->is_active, 'creator' => $purchase->creator->name,
            'date' => $purchase->created_at->timezone(config('app.timezone'))->format('d M Y H:i'),
            'receiver' => $purchase->receiver?->name, 'receivedAt' => $purchase->received_at?->timezone(config('app.timezone'))->format('d M Y H:i T'),
            'total' => $purchase->totalCost(),
            'items' => $purchase->items->map(fn (PurchaseItem $line): array => [
                'stock_item_id' => $line->stock_item_id, 'name' => $line->stockItem->name, 'unit' => $line->stockItem->base_unit,
                'active' => $line->stockItem->is_active, 'quantity' => $line->quantity, 'line_cost_idr' => $line->line_cost_idr,
            ]),
        ];
    }
}
