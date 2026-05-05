<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\DataTransferObjects\ClaudeRequest;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Exceptions\ClaudeUnavailableException;
use App\Domain\AI\Prompts\Commentary\CorrectV1;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\Commentary\DataTransferObjects\AICorrectCommentaryTextData;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-row commentary correction. Runs the `Commentary\CorrectV1` prompt
 * on `original` (or `content` if `original` is null because the row
 * pre-dates ETL) and writes the result into `plain` along with the
 * pass timestamp + prompt-version stamps.
 *
 * On upstream failure throws {@see ClaudeUnavailableException} which the
 * exception handler maps to a 502 with a `Retry-After` header.
 */
final class CorrectCommentaryTextAction
{
    public function __construct(
        private readonly ClaudeClient $client,
        private readonly PromptRegistry $registry,
    ) {}

    public function execute(AICorrectCommentaryTextData $data): CommentaryText
    {
        @set_time_limit((int) config('ai.request.timeout_seconds', 60));

        $text = $data->text;
        $sourceHtml = (string) ($text->original ?? $text->content);
        $language = $text->commentary->language;

        $prompt = $this->registry->get(CorrectV1::NAME, CorrectV1::VERSION);

        $response = $this->client->send(new ClaudeRequest(
            promptName: CorrectV1::NAME,
            promptVersion: CorrectV1::VERSION,
            model: $prompt->model() ?? (string) config('ai.model.default'),
            systemPrompt: $prompt->systemPrompt(),
            userMessage: $prompt->userMessage([
                'html' => $sourceHtml,
                'language' => $language,
            ]),
            subjectType: 'commentary_text',
            subjectId: (int) $text->id,
            triggeredByUserId: $data->triggeredByUserId,
        ));

        if ($response->status !== AiCallStatus::Ok) {
            throw new ClaudeUnavailableException(
                retryAfterSeconds: 30,
                aiCallId: $response->aiCallId,
            );
        }

        DB::transaction(function () use ($text, $response, $sourceHtml): void {
            $updates = [
                'plain' => $response->content,
                'ai_corrected_at' => Carbon::now(),
                'ai_corrected_prompt_version' => CorrectV1::VERSION,
            ];

            // First-pass-after-ETL safety net: if the row arrived from
            // Symfony with no `original` populated yet, freeze the
            // pre-correction HTML there so the audit trail is complete.
            if ($text->original === null || $text->original === '') {
                $updates['original'] = $sourceHtml;
            }

            $text->forceFill($updates)->save();
        });

        return $text->refresh();
    }
}
