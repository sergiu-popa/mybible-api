<?php

declare(strict_types=1);

namespace App\Domain\AI\DataTransferObjects;

/**
 * Envelope passed into the Claude HTTP wrapper. The system prompt is the
 * cached block; the user message is per-call (cache-bypass).
 */
final readonly class ClaudeRequest
{
    public function __construct(
        public string $promptName,
        public string $promptVersion,
        public string $model,
        public string $systemPrompt,
        public string $userMessage,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?int $triggeredByUserId = null,
        public int $maxTokens = 4096,
    ) {}
}
