<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Illuminate\Auth\AuthenticationException;

final class InvalidCredentialsException extends AuthenticationException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }
}
