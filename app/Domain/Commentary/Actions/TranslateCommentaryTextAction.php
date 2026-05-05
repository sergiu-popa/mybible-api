<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\DataTransferObjects\ClaudeRequest;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Exceptions\ClaudeUnavailableException;
use App\Domain\AI\Prompts\Commentary\TranslateV1;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\Commentary\DataTransferObjects\AIAddReferencesCommentaryTextData;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-row translation: clones a single source `CommentaryText` into the
 * target commentary, runs `Commentary\TranslateV1` to populate `plain`,
 * then runs `AddReferencesCommentaryTextAction` to populate
 * `with_references` against the target language's default Bible version.
 */
final class TranslateCommentaryTextAction
{
    public function __construct(
        private readonly ClaudeClient $client,
        private readonly PromptRegistry $registry,
        private readonly AddReferencesCommentaryTextAction $addReferences,
    ) {}

    public function execute(
        Commentary $target,
        CommentaryText $source,
        ?int $triggeredByUserId = null,
    ): CommentaryText {
        @set_time_limit((int) config('ai.request.timeout_seconds', 60));

        $sourceLanguage = $source->commentary->language;
        $targetLanguage = $target->language;
        $sourcePlain = (string) $source->plain;

        $prompt = $this->registry->get(TranslateV1::NAME, TranslateV1::VERSION);

        $response = $this->client->send(new ClaudeRequest(
            promptName: TranslateV1::NAME,
            promptVersion: TranslateV1::VERSION,
            model: $prompt->model() ?? (string) config('ai.model.default'),
            systemPrompt: $prompt->systemPrompt(),
            userMessage: $prompt->userMessage([
                'html' => $sourcePlain,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
            ]),
            subjectType: 'commentary_text',
            subjectId: (int) $source->id,
            triggeredByUserId: $triggeredByUserId,
        ));

        if ($response->status !== AiCallStatus::Ok) {
            throw new ClaudeUnavailableException(
                retryAfterSeconds: 30,
                aiCallId: $response->aiCallId,
            );
        }

        $translated = DB::transaction(function () use ($target, $source, $sourcePlain, $response): CommentaryText {
            /** @var CommentaryText $row */
            $row = $target->texts()->create([
                'book' => $source->book,
                'chapter' => $source->chapter,
                'position' => $source->position,
                'verse_from' => $source->verse_from,
                'verse_to' => $source->verse_to,
                'verse_label' => $source->verse_label,
                // `content` legacy column mirrors `plain` so existing
                // public reader code keeps working until the resolved
                // chain is wired everywhere.
                'content' => $response->content,
                'original' => $sourcePlain,
                'plain' => $response->content,
                'ai_corrected_at' => Carbon::now(),
                'ai_corrected_prompt_version' => TranslateV1::VERSION,
            ]);

            return $row;
        });

        // Reference linking against the target language is an independent
        // pass (separate prompt, separate audit row). We chain it here so
        // the orchestrating action contracts the translation pipeline as
        // a single per-row unit, but each call still records its own
        // `ai_calls` row for auditability.
        return $this->addReferences->execute(new AIAddReferencesCommentaryTextData(
            text: $translated,
            triggeredByUserId: $triggeredByUserId,
        ));
    }
}
