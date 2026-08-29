<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Illuminate\Support\Collection;

class InventoryStatus
{
    public static function forBalance(InventoryBalance $balance): string
    {
        $quantity = (float) $balance->quantity;
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        $batches = self::openBatches($balance->item_id, $balance->store_id, $balance->item?->batches);

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->status === 'quarantined')) {
            return 'quarantined';
        }

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->isExpired() || $batch->status === 'expired')) {
            return 'expired';
        }

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->status === 'reserved')) {
            return 'reserved';
        }

        if ($batches->contains(function (InventoryBatch $batch) {
            return $batch->expiry_date
                && $batch->expiry_date->toDateString() <= now()->addDays(90)->toDateString();
        })) {
            return 'expiring';
        }

        $reorder = (int) ($balance->item?->reorder_level ?? 0);
        if ($reorder > 0 && $quantity <= $reorder) {
            return 'low_stock';
        }

        return 'available';
    }

    public static function forItem(InventoryItem $item, float $quantity): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        $batches = self::openBatches($item->id, null, $item->relationLoaded('batches') ? $item->batches : null);

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->status === 'quarantined')) {
            return 'quarantined';
        }

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->isExpired() || $batch->status === 'expired')) {
            return 'expired';
        }

        if ($batches->contains(fn (InventoryBatch $batch) => $batch->status === 'reserved')) {
            return 'reserved';
        }

        if ($batches->contains(function (InventoryBatch $batch) {
            return $batch->expiry_date
                && $batch->expiry_date->toDateString() <= now()->addDays(90)->toDateString();
        })) {
            return 'expiring';
        }

        if ($item->reorder_level > 0 && $quantity <= $item->reorder_level) {
            return 'low_stock';
        }

        return 'available';
    }

    public static function valuation(InventoryBalance $balance): int
    {
        $item = $balance->item;
        if ($item?->tracks_batch) {
            return (int) InventoryBatch::query()
                ->where('item_id', $balance->item_id)
                ->where('store_id', $balance->store_id)
                ->selectRaw('coalesce(sum(quantity * unit_cost), 0) as value')
                ->value('value');
        }

        return (int) round((float) $balance->quantity * (int) ($item?->cost_price ?? 0));
    }

    private static function openBatches(string $itemId, ?string $storeId, mixed $loaded): Collection
    {
        if ($loaded instanceof Collection) {
            return $loaded->filter(function (InventoryBatch $batch) use ($storeId) {
                return (float) $batch->quantity > 0 && (! $storeId || $batch->store_id === $storeId);
            })->values();
        }

        return InventoryBatch::query()
            ->where('item_id', $itemId)
            ->when($storeId, fn ($query, $id) => $query->where('store_id', $id))
            ->where('quantity', '>', 0)
            ->get();
    }
}
