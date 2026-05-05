<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use RuntimeException;

final class UnknownPromptException extends RuntimeException
{
    public static function for(string $name, string $version): self
    {
        return new self(sprintf('Unknown prompt: %s@%s', $name, $version));
    }
}
