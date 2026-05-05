<?php

declare(strict_types=1);

namespace App\Domain\AI\DataTransferObjects;

use App\Domain\AI\Enums\AiCallStatus;

final readonly class ClaudeResponse
{
    public function __construct(
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheCreationInputTokens,
        public int $cacheReadInputTokens,
        public int $latencyMs,
        public AiCallStatus $status,
        public ?string $errorMessage,
        public int $aiCallId,
    ) {}
}
