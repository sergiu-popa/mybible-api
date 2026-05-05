<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when an upstream Anthropic call cannot be completed after the
 * configured retry budget. The exception handler maps it to a 502 with a
 * `Retry-After` header derived from `$retryAfterSeconds`.
 */
final class ClaudeUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds = 30,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
