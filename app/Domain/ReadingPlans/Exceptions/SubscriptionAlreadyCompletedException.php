<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Exceptions;

use RuntimeException;

final class SubscriptionAlreadyCompletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cannot abandon a completed subscription.');
    }
}
