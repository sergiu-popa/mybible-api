<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when an upstream Anthropic call cannot be completed after the
 * configured retry budget. The exception handler maps it to a 502 with a
 * `Retry-After` header derived from `$retryAfterSeconds`.
 *
 * The user-facing message is intentionally generic — full upstream error
 * detail lives only in `ai_calls.error_message` keyed by `$aiCallId`.
 */
final class ClaudeUnavailableException extends RuntimeException
{
    public const PUBLIC_MESSAGE = 'Upstream AI service is unavailable.';

    public function __construct(
        public readonly int $retryAfterSeconds = 30,
        public readonly ?int $aiCallId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(self::PUBLIC_MESSAGE, 0, $previous);
    }
}
