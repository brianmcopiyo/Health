<?php

namespace App\Support\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class ExportTree
{
    public const PARENT_LIMIT = 200;

    public const CHILD_LIMIT = 80;

    public const DEPTH = 3;

    public static function node(Model $model, User $user, int $depth = self::DEPTH): array
    {
        $schema = ExportRelations::schema($model);
        if (! empty($schema['with'])) {
            $model->loadMissing($schema['with']);
        }
        $groups = [];
        foreach ($schema['children'] ?? [] as $child) {
            if (! empty($child['subject']) && ! $user->hasPermission('read', $child['subject'])) {
                continue;
            }
            [$items, $total] = self::related($model, $child);
            $records = [];
            if ($depth > 1 && ! empty($child['nest'])) {
                foreach ($items as $item) {
                    if ($item instanceof Model && ExportRelations::supports($item)) {
                        $records[] = self::node($item, $user, $depth - 1);
                    }
                }
            }
            $groups[] = [
                'title' => $child['title'],
                'columns' => $child['columns'] ?? [],
                'rows' => $items->map($child['map'])->values()->all(),
                'total' => $total,
                'records' => $records,
            ];
        }

        return [
            'title' => ($schema['title'])($model),
            'facts' => ($schema['facts'])($model),
            'groups' => $groups,
        ];
    }

    private static function related(Model $model, array $child): array
    {
        $name = $child['relation'] ?? '';
        if ($name === '' || ! method_exists($model, $name)) {
            return [collect(), 0];
        }

        $relation = $model->{$name}();
        if (! $relation instanceof Relation) {
            return [collect(), 0];
        }

        if ($relation instanceof HasOne) {
            $row = $relation->getResults();
            $items = $row ? collect([$row]) : collect();

            return [$items, $items->count()];
        }

        $query = $relation;
        $order = $child['order'] ?? null;
        if (($child['direction'] ?? 'desc') === 'asc' && $order) {
            $query = $query->orderBy($order);
        } elseif ($order) {
            $query = $query->latest($order);
        } else {
            $query = $query->latest();
        }

        if (! empty($child['with'])) {
            $query = $query->with($child['with']);
        }

        $total = (clone $query)->count();
        $items = $query->limit(self::CHILD_LIMIT)->get();

        if (! empty($child['through'])) {
            $items = $items->map(fn ($row) => $row->{$child['through']})
                ->filter()
                ->unique(fn ($row) => $row instanceof Model ? $row->getKey() : spl_object_id($row))
                ->values();
            $total = $items->count();
        }

        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        return [$items, $total];
    }
}
