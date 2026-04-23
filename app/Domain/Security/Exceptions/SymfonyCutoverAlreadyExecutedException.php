<?php

declare(strict_types=1);

namespace App\Domain\Security\Exceptions;

use RuntimeException;

/**
 * Thrown when the Symfony cutover forced-logout action is invoked
 * after a prior successful run. The cutover must happen exactly once;
 * a double-run would write a duplicate audit row and revoke any
 * legitimate tokens issued between the two runs.
 */
final class SymfonyCutoverAlreadyExecutedException extends RuntimeException
{
    public function __construct(string $message = 'Symfony cutover forced logout has already been executed.')
    {
        parent::__construct($message);
    }
}
