<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Support\Facades\DB;

final class ReorderResourceCategoriesAction
{
    /**
     * Persists the given full ordering of category ids. Idempotent: callers
     * always send the complete current order (one wire format per the
     * E-02 backlog brief), so a partial replay just re-issues the same
     * write.
     *
     * @param  list<int>  $ids
     */
    public function execute(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                ResourceCategory::query()
                    ->whereKey($id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
