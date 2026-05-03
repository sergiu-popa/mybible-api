<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use App\Domain\Shared\Enums\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderResourceBooksAction
{
    /**
     * @param  list<int>  $ids
     */
    public function execute(Language $language, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = ResourceBook::query()
            ->where('language', $language->value)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to the requested language.'],
            ]);
        }

        DB::transaction(function () use ($language, $ids): void {
            foreach ($ids as $position => $id) {
                ResourceBook::query()
                    ->whereKey($id)
                    ->where('language', $language->value)
                    ->update(['position' => $position + 1]);
            }
        });

        Cache::tags(ResourceBooksCacheKeys::tagsForList())->flush();
    }
}
