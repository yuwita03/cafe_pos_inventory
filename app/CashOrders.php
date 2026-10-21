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
        return $this->place($actor, $data);
    }

    /** @param array<string, mixed> $data */
    public function guestCheckout(array $data): Order
    {
        return $this->place(null, $data);
    }

    /** @param array<string, mixed> $data */
    private function place(?User $actor, array $data): Order
    {
        $isQris = $actor !== null && ($data['payment_method'] ?? 'cash') === 'qris';
        $isPaidCash = $actor !== null && ! $isQris;
        $data['customer_name'] = trim($data['customer_name']);
        $data['customer_email'] = isset($data['customer_email']) ? trim($data['customer_email']) : null;
        $data['table_id'] = isset($data['table_id']) ? (int) $data['table_id'] : null;
        $data['expected_total_idr'] = (int) $data['expected_total_idr'];
        $data['cash_received_idr'] = $isPaidCash ? (int) $data['cash_received_idr'] : null;
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

        return DB::transaction(function () use ($actor, $data, $fingerprint, $isQris, $isPaidCash): Order {
            if ($actor) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            }
            $previous = Order::where('checkout_key', $data['checkout_key'])->first();
            if ($previous) {
                if ($previous->created_by_user_id !== $actor?->id || $previous->checkout_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['checkout_key' => 'Kunci checkout sudah digunakan untuk pesanan berbeda.']);
                }

                return $previous;
            }
            if ($isQris && ! app(MidtransQris::class)->available()) {
                throw ValidationException::withMessages(['payment_method' => 'QRIS sandbox belum dikonfigurasi. Gunakan tunai untuk pesanan baru.']);
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
            $stockProducts = [];
            $total = 0;
            foreach ($data['items'] as $line) {
                $variant = $variants->get($line['variant_id']);
                if (! $variant) {
                    throw ValidationException::withMessages(['items' => 'Varian tidak ditemukan. Muat ulang menu.']);
                }
                foreach (app(InventoryRequirements::class)->calculate($variant->id, $line['option_ids'], $line['quantity']) as $required) {
                    $id = $required['stockId'];
                    $stockProducts[$id][$variant->product->name] = true;
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
            if ($isPaidCash && $data['cash_received_idr'] < $total) {
                throw ValidationException::withMessages(['cash_received_idr' => 'Uang tunai tidak mencukupi total pesanan.']);
            }
            $stocks = StockItem::whereIn('id', array_keys($requirements))->orderBy('id')->lockForUpdate()->get();
            $reserved = DB::table('stock_reservations')->whereIn('stock_item_id', array_keys($requirements))->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->groupBy('stock_item_id')->selectRaw('stock_item_id, SUM(quantity) AS quantity')->pluck('quantity', 'stock_item_id');
            foreach ($stocks as $stock) {
                if (! $stock->is_active || StockQuantity::milli($stock->on_hand_qty) - StockQuantity::milli($reserved[$stock->id] ?? '0') < $requirements[$stock->id]) {
                    $productNames = implode(', ', array_keys($stockProducts[$stock->id]));
                    throw ValidationException::withMessages(['items' => "Stok untuk {$productNames} tidak lagi mencukupi. Silakan periksa keranjang."]);
                }
            }
            $order = Order::create([
                'table_id' => $data['table_id'], 'created_by_user_id' => $actor?->id,
                'customer_name' => $data['customer_name'], 'customer_email' => $data['customer_email'],
                'source' => $actor ? 'cashier' : 'table_qr', 'fulfillment_type' => $actor ? $data['fulfillment_type'] : 'dine_in',
                'status' => $isPaidCash ? 'queued' : 'awaiting_payment', 'public_token' => Str::random(64), 'checkout_key' => $data['checkout_key'],
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
            $expiresAt = $actor ? null : now()->addMinutes(30);
            foreach ($requirements as $stockId => $milli) {
                if ($milli > 0) {
                    DB::table('stock_reservations')->insert(['order_id' => $order->id, 'stock_item_id' => $stockId, 'quantity' => StockQuantity::decimal($milli), 'status' => 'active', 'expires_at' => $expiresAt]);
                }
            }
            DB::table('payments')->insert([
                'order_id' => $order->id, 'method' => $isQris ? 'qris' : 'cash', 'amount_idr' => $total,
                'status' => $isPaidCash ? 'paid' : 'pending', 'paid_at' => $isPaidCash ? now() : null,
                'created_at' => now(), 'cash_received_idr' => $data['cash_received_idr'],
                'change_idr' => $isPaidCash ? $data['cash_received_idr'] - $total : null,
                'provider' => $isQris ? 'midtrans_sandbox' : null,
                'provider_reference' => $isQris ? 'qris-'.Str::uuid() : null,
                'qris_expires_at' => $isQris ? now()->addMinutes(15) : null,
            ]);

            return $order;
        }, 3);
    }

    public function expire(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'awaiting_payment') {
                return;
            }
            $expiresAt = DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->min('expires_at');
            if (! $expiresAt || Carbon::parse($expiresAt)->isFuture()) {
                return;
            }
            DB::table('payments')->where('order_id', $id)->where('status', 'pending')->update(['status' => 'expired']);
            DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->update(['status' => 'released']);
            $order->update(['status' => 'cancelled']);
        }, 3);
    }

    public function expirePending(): void
    {
        DB::table('stock_reservations')->where('status', 'active')->where('expires_at', '<=', now())->distinct()->pluck('order_id')->each(fn ($id) => $this->expire((int) $id));
    }

    public function confirmGuestCash(int $id, int $received): Order
    {
        $this->expire($id);

        return DB::transaction(function () use ($id, $received): Order {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->source !== 'table_qr') {
                throw ValidationException::withMessages(['order' => 'Pesanan bukan pesanan tunai pelanggan.']);
            }
            $payment = DB::table('payments')->where('order_id', $id)->where('method', 'cash')->lockForUpdate()->first();
            if (in_array($order->status, ['queued', 'preparing', 'ready', 'completed'], true) && $payment?->status === 'paid' && (int) $payment->cash_received_idr === $received) {
                return $order;
            }
            $expiresAt = DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->min('expires_at');
            if ($order->status !== 'awaiting_payment' || $payment?->status !== 'pending' || ! $expiresAt || ! Carbon::parse($expiresAt)->isFuture()) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah dibayar atau kedaluwarsa. Muat ulang daftar pesanan.']);
            }
            if ($received < $order->total_idr) {
                throw ValidationException::withMessages(['cash_received_idr' => 'Uang tunai tidak mencukupi total pesanan.']);
            }
            DB::table('payments')->where('id', $payment->id)->update(['status' => 'paid', 'cash_received_idr' => $received, 'change_idr' => $received - $order->total_idr, 'paid_at' => now()]);
            DB::table('stock_reservations')->where('order_id', $id)->where('status', 'active')->update(['expires_at' => null]);
            $order->update(['status' => 'queued']);

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
        $expiresAt = DB::table('stock_reservations')->whereIn('order_id', $ids)->where('status', 'active')->whereNotNull('expires_at')->groupBy('order_id')->selectRaw('order_id, MIN(expires_at) AS expires_at')->pluck('expires_at', 'order_id');
        $grouped = $items->groupBy('order_id');

        $result = $orders->map(function (Order $order) use ($tables, $grouped, $options, $payments, $expiresAt): array {
            $attempts = $payments->get($order->id, collect());
            $payment = $attempts->firstWhere('status', 'paid') ?? $attempts->last();

            return [
                'id' => $order->id, 'number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), 'total' => $order->total_idr,
                'customerName' => $order->customer_name, 'customerEmail' => $order->customer_email, 'source' => $order->source, 'fulfillment' => $order->fulfillment_type,
                'table' => $tables->get($order->table_id), 'tableId' => $order->table_id, 'status' => $order->status, 'paymentStatus' => $payment->status ?? 'pending',
                'method' => $payment->method ?? 'cash', 'reference' => $payment?->provider_reference,
                'received' => $payment?->cash_received_idr === null ? null : (int) $payment->cash_received_idr,
                'change' => $payment?->change_idr === null ? null : (int) $payment->change_idr,
                'createdAt' => $order->created_at->toIso8601String(), 'paidAt' => $payment?->paid_at ? Carbon::parse($payment->paid_at)->toIso8601String() : null,
                'expiresAt' => isset($expiresAt[$order->id]) ? Carbon::parse($expiresAt[$order->id])->toIso8601String() : null,
                'qris' => $payment?->method === 'qris' ? [
                    'state' => $payment->qr_image_url ? 'ready' : 'checking',
                    'qrUrl' => $payment->qr_image_url,
                    'expiresAt' => $payment->qris_expires_at ? Carbon::parse($payment->qris_expires_at)->toIso8601String() : null,
                    'serverTime' => now()->toIso8601String(),
                ] : null,
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
