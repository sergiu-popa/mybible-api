<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Exceptions;

use RuntimeException;

final class OlympiadAttemptThemeMismatchException extends RuntimeException
{
    public function __construct(string $message = 'A submitted question does not belong to this attempt\'s theme.')
    {
        parent::__construct($message);
    }
}
