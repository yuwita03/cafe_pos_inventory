<?php

namespace App;

use App\Models\CafeTable;
use App\Models\Option;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

final class CashOrders
{
    /** @param array<string, mixed> $data */
    public function checkout(User $actor, array $data): Order
    {
        $data['customer_name'] = trim($data['customer_name']);
        $data['table_id'] = isset($data['table_id']) ? (int) $data['table_id'] : null;
        $data['expected_total_idr'] = (int) $data['expected_total_idr'];
        $data['cash_received_idr'] = (int) $data['cash_received_idr'];
        foreach ($data['items'] as &$line) {
            $line['variant_id'] = (int) $line['variant_id'];
            $line['quantity'] = (int) $line['quantity'];
            $line['option_ids'] = array_map('intval', $line['option_ids']);
            if (count($line['option_ids']) !== count(array_unique($line['option_ids']))) {
                throw ValidationException::withMessages(['items' => 'Opsi yang sama tidak boleh dipilih dua kali pada satu item.']);
            }
            sort($line['option_ids']);
        }
        unset($line);
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $data, $fingerprint): Order {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $previous = Order::where('checkout_key', $data['checkout_key'])->first();
            if ($previous) {
                if ($previous->created_by_user_id !== $actor->id || $previous->checkout_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['checkout_key' => 'Kunci checkout sudah digunakan untuk pesanan berbeda.']);
                }

                return $previous;
            }
            if ($data['customer_name'] === '') {
                throw ValidationException::withMessages(['customer_name' => 'Nama pelanggan wajib diisi.']);
            }
            if ($data['table_id'] !== null && ! CafeTable::whereKey($data['table_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['table_id' => 'Pilih meja yang aktif.']);
            }
            $variantIds = array_unique(array_column($data['items'], 'variant_id'));
            $variants = ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $optionIds = array_unique(array_merge(...array_column($data['items'], 'option_ids')));
            $options = Option::whereIn('id', $optionIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $snapshots = [];
            $requirements = [];
            $total = 0;
            foreach ($data['items'] as $line) {
                $variant = $variants->get($line['variant_id']);
                if (! $variant) {
                    throw ValidationException::withMessages(['items' => 'Varian tidak ditemukan. Muat ulang menu.']);
                }
                foreach (app(InventoryRequirements::class)->calculate($variant->id, $line['option_ids'], $line['quantity']) as $required) {
                    $id = $required['stockId'];
                    $requirements[$id] = ($requirements[$id] ?? 0) + StockQuantity::milli($required['total']);
                    if ($requirements[$id] > StockQuantity::MAX_MILLI) {
                        throw ValidationException::withMessages(['items' => 'Kebutuhan stok melebihi batas yang didukung.']);
                    }
                }
                $selected = $options->only($line['option_ids'])->values();
                $unitPrice = $variant->price_idr + (int) $selected->sum('price_delta_idr');
                $total += $unitPrice * $line['quantity'];
                if ($total > 9007199254740991) {
                    throw ValidationException::withMessages(['items' => 'Total harga melebihi batas yang didukung.']);
                }
                $snapshots[] = ['variant' => $variant, 'unitPrice' => $unitPrice, 'quantity' => $line['quantity'], 'options' => $selected];
            }
            if ($total !== $data['expected_total_idr']) {
                throw ValidationException::withMessages(['expected_total_idr' => 'Harga menu berubah. Muat ulang menu dan periksa ulang pesanan sebelum membayar.']);
            }
            if ($data['cash_received_idr'] < $total) {
                throw ValidationException::withMessages(['cash_received_idr' => 'Uang tunai tidak mencukupi total pesanan.']);
            }
            $stocks = StockItem::whereIn('id', array_keys($requirements))->orderBy('id')->lockForUpdate()->get();
            $reserved = DB::table('stock_reservations')->whereIn('stock_item_id', array_keys($requirements))->where('status', 'active')->groupBy('stock_item_id')->selectRaw('stock_item_id, SUM(quantity) AS quantity')->pluck('quantity', 'stock_item_id');
            foreach ($stocks as $stock) {
                if (! $stock->is_active || StockQuantity::milli($stock->on_hand_qty) - StockQuantity::milli($reserved[$stock->id] ?? '0') < $requirements[$stock->id]) {
                    throw ValidationException::withMessages(['items' => "Stok {$stock->name} tidak mencukupi. Periksa pesanan atau stok tersedia."]);
                }
            }
            $order = Order::create([
                'table_id' => $data['table_id'], 'created_by_user_id' => $actor->id,
                'customer_name' => $data['customer_name'], 'source' => 'cashier', 'fulfillment_type' => $data['fulfillment_type'],
                'status' => 'queued', 'public_token' => Str::random(64), 'checkout_key' => $data['checkout_key'],
                'checkout_fingerprint' => $fingerprint, 'total_idr' => $total,
            ]);
            foreach ($snapshots as $snapshot) {
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id' => $order->id, 'product_variant_id' => $snapshot['variant']->id,
                    'product_name_snapshot' => $snapshot['variant']->product->name, 'variant_name_snapshot' => $snapshot['variant']->name,
                    'unit_price_idr' => $snapshot['unitPrice'], 'quantity' => $snapshot['quantity'],
                ]);
                foreach ($snapshot['options'] as $option) {
                    DB::table('order_item_options')->insert(['order_item_id' => $itemId, 'option_id' => $option->id, 'option_name_snapshot' => $option->name, 'price_delta_idr' => $option->price_delta_idr]);
                }
            }
            foreach ($requirements as $stockId => $milli) {
                if ($milli > 0) {
                    DB::table('stock_reservations')->insert(['order_id' => $order->id, 'stock_item_id' => $stockId, 'quantity' => StockQuantity::decimal($milli), 'status' => 'active', 'expires_at' => null]);
                }
            }
            DB::table('payments')->insert(['order_id' => $order->id, 'method' => 'cash', 'amount_idr' => $total, 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'cash_received_idr' => $data['cash_received_idr'], 'change_idr' => $data['cash_received_idr'] - $total]);

            return $order;
        }, 3);
    }

    public function advance(int $id, string $expected, User $actor): void
    {
        DB::transaction(function () use ($id, $expected, $actor): void {
            $order = Order::query()->lockForUpdate()->findOrFail($id);
            $next = ['queued' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'][$expected];
            if (! DB::table('payments')->where('order_id', $id)->where('status', 'paid')->where('amount_idr', $order->total_idr)->exists()) {
                throw ValidationException::withMessages(['status' => 'Pesanan belum memiliki pembayaran terkonfirmasi.']);
            }
            if ($order->status === $next) {
                return;
            }
            if ($order->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'Status pesanan sudah berubah. Muat ulang antrean sebelum melanjutkan.']);
            }
            if ($expected === 'queued') {
                $reservations = DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->orderBy('stock_item_id')->get();
                if ($reservations->isEmpty()) {
                    throw ValidationException::withMessages(['status' => 'Reservasi stok pesanan tidak tersedia. Hubungi owner.']);
                }
                StockItem::whereIn('id', $reservations->pluck('stock_item_id'))->orderBy('id')->lockForUpdate()->get();
                DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->update(['status' => 'consumed']);
                foreach ($reservations as $reservation) {
                    app(InventoryStock::class)->adjust((int) $reservation->stock_item_id, StockQuantity::decimal(-StockQuantity::milli($reservation->quantity)), 'Order ORD-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT).' mulai dibuat', (string) Str::uuid(), $actor->id);
                }
            }
            $order->update(['status' => $next]);
        }, 3);
    }

    /** @param Collection<int, Order> $orders
     * @return list<array<string, mixed>>
     */
    public function present(Collection $orders): array
    {
        $ids = $orders->pluck('id');
        $tables = CafeTable::whereIn('id', $orders->pluck('table_id')->filter())->pluck('label', 'id');
        $items = DB::table('order_items')->whereIn('order_id', $ids)->orderBy('id')->get();
        $options = DB::table('order_item_options')->whereIn('order_item_id', $items->pluck('id'))->orderBy('id')->get()->groupBy('order_item_id');
        $payments = DB::table('payments')->whereIn('order_id', $ids)->orderBy('id')->get()->groupBy('order_id');
        $grouped = $items->groupBy('order_id');

        $result = $orders->map(function (Order $order) use ($tables, $grouped, $options, $payments): array {
            $attempts = $payments->get($order->id, collect());
            $payment = $attempts->firstWhere('status', 'paid') ?? $attempts->last();

            return [
                'id' => $order->id, 'number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                'customerName' => $order->customer_name, 'source' => $order->source, 'fulfillment' => $order->fulfillment_type,
                'table' => $tables->get($order->table_id), 'status' => $order->status, 'paymentStatus' => $payment->status ?? 'pending',
                'method' => $payment->method ?? 'cash', 'reference' => $payment?->provider_reference,
                'received' => $payment?->cash_received_idr === null ? null : (int) $payment->cash_received_idr,
                'change' => $payment?->change_idr === null ? null : (int) $payment->change_idr,
                'createdAt' => $order->created_at->toIso8601String(), 'paidAt' => $payment?->paid_at ? Carbon::parse($payment->paid_at)->toIso8601String() : null,
                'lines' => $grouped->get($order->id, collect())->map(function (stdClass $item) use ($options): array {
                    $choices = $options->get($item->id, collect());

                    return ['key' => (string) $item->id, 'productId' => 0, 'variantId' => (int) $item->product_variant_id,
                        'optionIds' => $choices->pluck('option_id')->map(fn ($id): int => (int) $id)->all(),
                        'productName' => $item->product_name_snapshot, 'variantName' => $item->variant_name_snapshot,
                        'optionNames' => $choices->pluck('option_name_snapshot')->all(), 'unitPrice' => (int) $item->unit_price_idr, 'quantity' => (int) $item->quantity];
                })->all(),
            ];
        })->values()->all();

        return array_values($result);
    }
}
