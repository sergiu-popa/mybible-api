<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Exceptions;

use RuntimeException;

final class OlympiadAnswerNotInQuestionException extends RuntimeException
{
    public function __construct(string $message = 'The selected answer does not belong to the given question.')
    {
        parent::__construct($message);
    }
}
