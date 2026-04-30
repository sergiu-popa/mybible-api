<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderResourceCategoriesAction
{
    /**
     * Persist a full ordering of category ids. Every id must resolve to
     * an existing `resource_categories` row; a mismatch raises
     * `ValidationException` so admin clients with stale ids see a 422
     * instead of a silent no-op.
     *
     * @param  list<int>  $ids
     */
    public function execute(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = ResourceCategory::query()
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to known resource categories.'],
            ]);
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
