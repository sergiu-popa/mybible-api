<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderEducationalResourcesAction
{
    /**
     * Persist a full ordering of resource ids inside a single category.
     * Every id in `$ids` must belong to `$category`; a mismatch raises
     * `ValidationException` so admin clients with stale ids see 422
     * instead of a silent partial reorder.
     *
     * @param  list<int>  $ids
     */
    public function execute(ResourceCategory $category, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = EducationalResource::query()
            ->where('resource_category_id', $category->id)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to the target category.'],
            ]);
        }

        DB::transaction(function () use ($category, $ids): void {
            foreach ($ids as $position => $id) {
                EducationalResource::query()
                    ->whereKey($id)
                    ->where('resource_category_id', $category->id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
