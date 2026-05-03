<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Exceptions;

use RuntimeException;

final class OlympiadAttemptAlreadyFinishedException extends RuntimeException
{
    public function __construct(string $message = 'This attempt has already been finished.')
    {
        parent::__construct($message);
    }
}
