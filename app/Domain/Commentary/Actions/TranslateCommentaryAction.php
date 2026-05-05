<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\DataTransferObjects\CommentaryData;
use App\Domain\Commentary\DataTransferObjects\TranslateCommentaryData;
use App\Domain\Commentary\Exceptions\CommentaryNotCorrectedException;
use App\Domain\Commentary\Exceptions\TranslationTargetExistsException;
use App\Domain\Commentary\Models\Commentary;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the `(source.id, target_language)` translation lifecycle:
 *
 * 1. Refuses to start when any source row's `plain` is null.
 * 2. Looks up an existing target by `(source_commentary_id, language)`.
 *    If absent, creates one. If present and `overwrite=false`, throws
 *    {@see TranslationTargetExistsException} (mapped to 409). If present
 *    and `overwrite=true`, deletes the target's existing rows.
 * 3. Returns the resolved target `Commentary`. The actual per-row
 *    translation is performed inside the batch job, which iterates the
 *    source rows and calls {@see TranslateCommentaryTextAction}.
 */
final class TranslateCommentaryAction
{
    public function __construct(
        private readonly CreateCommentaryAction $createCommentary,
    ) {}

    public function prepare(TranslateCommentaryData $data): Commentary
    {
        $source = Commentary::query()->with('texts')->findOrFail($data->sourceCommentaryId);

        $missing = $source->texts->first(fn ($text): bool => $text->plain === null || $text->plain === '');
        if ($missing !== null) {
            throw CommentaryNotCorrectedException::for((int) $source->id);
        }

        $existing = Commentary::query()
            ->where('source_commentary_id', $source->id)
            ->where('language', $data->targetLanguage)
            ->first();

        if ($existing instanceof Commentary && ! $data->overwrite) {
            throw TranslationTargetExistsException::for(
                (int) $source->id,
                $data->targetLanguage,
                (int) $existing->id,
            );
        }

        return DB::transaction(function () use ($source, $existing, $data): Commentary {
            if ($existing instanceof Commentary) {
                $existing->texts()->delete();

                return $existing;
            }

            return $this->createCommentary->execute(new CommentaryData(
                slug: $source->slug . '-' . $data->targetLanguage,
                name: (array) $source->name,
                abbreviation: $source->abbreviation,
                language: $data->targetLanguage,
                sourceCommentaryId: (int) $source->id,
            ));
        });
    }
}
