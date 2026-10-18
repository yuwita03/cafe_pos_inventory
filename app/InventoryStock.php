<?php

namespace App;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryStock
{
    /** @return array<int, int> */
    public function reservedQuantities(): array
    {
        return DB::table('stock_reservations')->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->groupBy('stock_item_id')->selectRaw('stock_item_id, SUM(quantity) AS quantity')
            ->pluck('quantity', 'stock_item_id')->map(fn ($quantity): int => StockQuantity::milli((string) $quantity))->all();
    }

    public function adjust(int $stockItemId, string $delta, string $reason, string $sourceKey, ?int $actorId): StockMovement
    {
        return DB::transaction(function () use ($stockItemId, $delta, $reason, $sourceKey, $actorId): StockMovement {
            $item = StockItem::query()->lockForUpdate()->findOrFail($stockItemId);
            $milli = StockQuantity::milli($delta);
            $previous = StockMovement::where('source_key', $sourceKey)->first();
            if ($previous) {
                if ($previous->stock_item_id !== $stockItemId || StockQuantity::milli($previous->quantity_delta) !== $milli || $previous->reason !== $reason || $previous->actor_user_id !== $actorId) {
                    throw ValidationException::withMessages(['source_key' => 'Kunci adjustment sudah dipakai untuk perubahan yang berbeda.']);
                }

                return $previous;
            }
            if (! $item->is_active || $milli === 0) {
                throw ValidationException::withMessages(['quantity_delta' => 'Pilih item aktif dan perubahan stok bukan nol.']);
            }
            $balance = StockQuantity::milli($item->on_hand_qty) + $milli;
            if ($balance < 0 || $balance > StockQuantity::MAX_MILLI) {
                throw ValidationException::withMessages(['quantity_delta' => 'Hasil stok harus antara 0 dan 999999999.999.']);
            }
            $reserved = DB::table('stock_reservations')->where('stock_item_id', $item->id)->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->sum('quantity');
            if ($balance < StockQuantity::milli((string) $reserved)) {
                throw ValidationException::withMessages(['quantity_delta' => 'Saldo tidak boleh lebih kecil dari stok yang direservasi pesanan.']);
            }
            $item->update(['on_hand_qty' => StockQuantity::decimal($balance)]);

            return $item->movements()->create([
                'quantity_delta' => StockQuantity::decimal($milli), 'reason' => $reason,
                'source_key' => $sourceKey, 'actor_user_id' => $actorId,
            ]);
        }, 3);
    }
}
