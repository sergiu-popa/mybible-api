<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\OlympiadCacheKeys;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderOlympiadQuestionsAction
{
    /**
     * Persist a full ordering of olympiad question ids inside the
     * `(language, book, chapters_from, chapters_to)` theme supplied by
     * the caller. Every id in `$ids` must resolve inside that theme; a
     * mismatch raises `ValidationException` so admin clients with stale
     * ids see a 422 instead of a silent partial reorder.
     *
     * @param  list<int>  $ids
     */
    public function execute(string $book, ChapterRange $range, Language $language, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = OlympiadQuestion::query()
            ->where('language', $language->value)
            ->where('book', $book)
            ->where('chapters_from', $range->from)
            ->where('chapters_to', $range->to)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to the target theme.'],
            ]);
        }

        DB::transaction(function () use ($book, $range, $language, $ids): void {
            foreach ($ids as $position => $id) {
                OlympiadQuestion::query()
                    ->whereKey($id)
                    ->where('language', $language->value)
                    ->where('book', $book)
                    ->where('chapters_from', $range->from)
                    ->where('chapters_to', $range->to)
                    ->update(['position' => $position + 1]);
            }
        });

        Cache::tags(OlympiadCacheKeys::tagsForTheme($book, $range, $language))->flush();
    }
}
