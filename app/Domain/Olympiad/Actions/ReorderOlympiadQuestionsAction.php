<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Support\Facades\DB;

final class ReorderOlympiadQuestionsAction
{
    /**
     * Persist a full ordering of olympiad question ids inside the theme
     * derived from the first id.
     *
     * The action picks up the theme tuple
     * `(language, book, chapters_from, chapters_to)` from the first id
     * in the list, then only touches questions that match that exact
     * tuple. Ids belonging to a different theme are silently ignored,
     * so a buggy admin client cannot accidentally reshuffle siblings of
     * another theme by mixing ids.
     *
     * @param  list<int>  $ids
     */
    public function execute(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $anchor = OlympiadQuestion::query()->whereKey($ids[0])->first();

        if ($anchor === null) {
            return;
        }

        DB::transaction(function () use ($anchor, $ids): void {
            foreach ($ids as $position => $id) {
                OlympiadQuestion::query()
                    ->whereKey($id)
                    ->where('language', $anchor->language->value)
                    ->where('book', $anchor->book)
                    ->where('chapters_from', $anchor->chapters_from)
                    ->where('chapters_to', $anchor->chapters_to)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
