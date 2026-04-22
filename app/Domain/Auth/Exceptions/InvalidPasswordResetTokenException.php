<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

final class InvalidPasswordResetTokenException extends RuntimeException
{
    public function __construct(string $message = 'This password reset token is invalid.')
    {
        parent::__construct($message);
    }
}
