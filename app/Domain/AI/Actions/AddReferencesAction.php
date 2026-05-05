<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\DataTransferObjects\AddReferencesInput;
use App\Domain\AI\DataTransferObjects\AddReferencesOutput;
use App\Domain\AI\DataTransferObjects\ClaudeRequest;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Exceptions\ClaudeUnavailableException;
use App\Domain\AI\Prompts\AddReferences\V1 as AddReferencesV1;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\AI\Support\AddedReferencesValidator;
use App\Domain\AI\Support\AddReferencesVersionResolver;

/**
 * Synchronous AddReferences orchestration. One call from a row that
 * needs its HTML enriched with `<a class="reference">` links.
 *
 * On upstream failure, throws {@see ClaudeUnavailableException} which the
 * exception handler maps to a 502 with a `Retry-After` header.
 */
final class AddReferencesAction
{
    public function __construct(
        private readonly ClaudeClient $client,
        private readonly PromptRegistry $registry,
        private readonly AddReferencesVersionResolver $versionResolver,
        private readonly AddedReferencesValidator $validator,
    ) {}

    public function execute(AddReferencesInput $input): AddReferencesOutput
    {
        // Cap PHP wall-clock to match the upstream HTTP timeout so a slow
        // Anthropic call cannot exceed the SLA the sync endpoint promises.
        @set_time_limit((int) config('ai.request.timeout_seconds', 60));

        $version = $this->versionResolver->resolve(
            $input->language,
            $input->bibleVersionAbbreviation,
        );

        $prompt = $this->registry->get(AddReferencesV1::NAME, AddReferencesV1::VERSION);

        $response = $this->client->send(new ClaudeRequest(
            promptName: AddReferencesV1::NAME,
            promptVersion: AddReferencesV1::VERSION,
            model: $prompt->model() ?? (string) config('ai.model.default'),
            systemPrompt: $prompt->systemPrompt(),
            userMessage: $prompt->userMessage([
                'html' => $input->html,
                'language' => $input->language,
                'bible_version_abbreviation' => $version,
            ]),
            subjectType: $input->subjectType,
            subjectId: $input->subjectId,
            triggeredByUserId: $input->triggeredByUserId,
        ));

        if ($response->status !== AiCallStatus::Ok) {
            throw new ClaudeUnavailableException(
                retryAfterSeconds: 30,
                aiCallId: $response->aiCallId,
            );
        }

        $validated = $this->validator->validate($response->content);

        return new AddReferencesOutput(
            html: $validated['html'],
            referencesAdded: $validated['references_added'],
            promptVersion: AddReferencesV1::VERSION,
            aiCallId: $response->aiCallId,
        );
    }
}
