<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderCommentaryTextsAction
{
    /**
     * @param  list<int>  $orderedIds
     */
    public function execute(Commentary $commentary, string $book, int $chapter, array $orderedIds): void
    {
        $existing = $commentary->texts()
            ->where('book', $book)
            ->where('chapter', $chapter)
            ->pluck('id')
            ->all();

        sort($existing);
        $sorted = $orderedIds;
        sort($sorted);

        if ($existing !== $sorted) {
            throw ValidationException::withMessages([
                'ids' => 'Provided ids do not match the texts in this book and chapter.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            $offset = count($orderedIds) + 1;

            // Two-phase update to avoid colliding with the
            // (commentary_id, book, chapter, position) UNIQUE during the
            // shuffle: stage to high positions first, then settle into 1..n.
            foreach ($orderedIds as $index => $id) {
                CommentaryText::query()
                    ->where('id', $id)
                    ->update(['position' => $offset + $index]);
            }

            foreach ($orderedIds as $index => $id) {
                CommentaryText::query()
                    ->where('id', $id)
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
