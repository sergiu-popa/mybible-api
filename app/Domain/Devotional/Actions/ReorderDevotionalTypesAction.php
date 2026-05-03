<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\Models\DevotionalType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderDevotionalTypesAction
{
    /**
     * @param  list<int>  $ids
     */
    public function handle(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = DevotionalType::query()->whereIn('id', $ids)->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids are not valid devotional type ids.'],
            ]);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                DevotionalType::query()
                    ->whereKey($id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
