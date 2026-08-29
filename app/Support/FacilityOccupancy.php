<?php

namespace App\Support;

use App\Models\Facility;

class FacilityOccupancy
{
    public static function syncWard(?Facility $ward): void
    {
        if (! $ward) {
            return;
        }

        $ward->loadMissing('type');
        if ($ward->type?->slug !== 'ward') {
            return;
        }

        $beds = Facility::query()
            ->where('parent_id', $ward->id)
            ->whereHas('type', fn ($query) => $query->where('slug', 'bed'))
            ->lockForUpdate()
            ->get();

        if ($beds->isEmpty()) {
            return;
        }

        $capacity = (int) max(1, $beds->sum('capacity'));
        $used = (int) $beds->sum('current_utilization');
        $status = $ward->status;

        if (! in_array($status, ['maintenance', 'unavailable', 'reserved'], true)) {
            $status = $used >= $capacity ? 'occupied' : 'available';
        }

        $ward->forceFill([
            'capacity' => $capacity,
            'current_utilization' => $used,
            'status' => $status,
        ])->save();
    }

    public static function syncForBed(Facility $bed, ?string $previousParentId = null): void
    {
        $bed->loadMissing(['type', 'parent.type']);

        if ($bed->type?->slug !== 'bed') {
            return;
        }

        self::syncWard($bed->parent);

        if ($previousParentId && $previousParentId !== $bed->parent_id) {
            self::syncWard(Facility::query()->find($previousParentId));
        }
    }
}
