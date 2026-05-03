<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderResourceBookChaptersAction
{
    /**
     * @param  list<int>  $ids
     */
    public function execute(ResourceBook $book, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = ResourceBookChapter::query()
            ->where('resource_book_id', $book->id)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more chapter ids do not belong to the parent book.'],
            ]);
        }

        DB::transaction(function () use ($book, $ids): void {
            // Pass-1 target range must clear every existing position in the
            // book, including chapters omitted from $ids — otherwise the
            // unique (resource_book_id, position) index can collide on a
            // partial reorder.
            $offset = (int) ResourceBookChapter::query()
                ->where('resource_book_id', $book->id)
                ->max('position') + 1;

            foreach ($ids as $i => $id) {
                ResourceBookChapter::query()
                    ->whereKey($id)
                    ->where('resource_book_id', $book->id)
                    ->update(['position' => $offset + $i]);
            }

            foreach ($ids as $i => $id) {
                ResourceBookChapter::query()
                    ->whereKey($id)
                    ->where('resource_book_id', $book->id)
                    ->update(['position' => $i + 1]);
            }
        });

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($book->id))->flush();
    }
}
