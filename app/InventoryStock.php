<?php

namespace App;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryStock
{
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
            $item->update(['on_hand_qty' => StockQuantity::decimal($balance)]);

            return $item->movements()->create([
                'quantity_delta' => StockQuantity::decimal($milli), 'reason' => $reason,
                'source_key' => $sourceKey, 'actor_user_id' => $actorId,
            ]);
        }, 3);
    }
}
