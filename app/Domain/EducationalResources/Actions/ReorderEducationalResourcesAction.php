<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Support\Facades\DB;

final class ReorderEducationalResourcesAction
{
    /**
     * Persists the given full ordering of resource ids inside a single
     * category. Resources not belonging to the category are ignored, so
     * the action cannot accidentally reshuffle siblings of another
     * category if the caller mixes ids.
     *
     * @param  list<int>  $ids
     */
    public function execute(ResourceCategory $category, array $ids): void
    {
        if ($ids === []) {
            return;
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
