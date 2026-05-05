<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\AI\Actions\AddReferencesAction;
use App\Domain\AI\DataTransferObjects\AddReferencesInput;
use App\Domain\AI\Prompts\AddReferences\V1 as AddReferencesV1;
use App\Domain\Commentary\DataTransferObjects\AIAddReferencesCommentaryTextData;
use App\Domain\Commentary\Exceptions\CommentaryTextNotCorrectedException;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-row reference linker. Runs MBA-028's {@see AddReferencesAction}
 * against the row's `plain` HTML using the commentary's language → its
 * default Bible version (resolved inside `AddReferencesAction`).
 *
 * Refuses to run unless the row has been corrected first — reference
 * linking on the raw Symfony HTML wastes tokens (typos in book names
 * confuse the parser).
 */
final class AddReferencesCommentaryTextAction
{
    public function __construct(
        private readonly AddReferencesAction $addReferences,
    ) {}

    public function execute(AIAddReferencesCommentaryTextData $data): CommentaryText
    {
        $text = $data->text;
        $plain = $text->plain;

        if (! is_string($plain) || $plain === '') {
            throw CommentaryTextNotCorrectedException::for((int) $text->id);
        }

        $output = $this->addReferences->execute(new AddReferencesInput(
            html: $plain,
            language: $text->commentary->language,
            bibleVersionAbbreviation: null,
            subjectType: 'commentary_text',
            subjectId: (int) $text->id,
            triggeredByUserId: $data->triggeredByUserId,
        ));

        DB::transaction(function () use ($text, $output): void {
            $text->forceFill([
                'with_references' => $output->html,
                'ai_referenced_at' => Carbon::now(),
                'ai_referenced_prompt_version' => AddReferencesV1::VERSION,
            ])->save();
        });

        return $text->refresh();
    }
}
