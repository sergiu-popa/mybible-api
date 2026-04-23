<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Exceptions;

use RuntimeException;

final class OlympiadThemeNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Olympiad theme not found.')
    {
        parent::__construct($message);
    }
}
