<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Exceptions;

use RuntimeException;

final class SubscriptionNotCompletableException extends RuntimeException
{
    /**
     * @param  array<int, int>  $pendingPositions
     */
    public function __construct(public readonly array $pendingPositions)
    {
        parent::__construct('Subscription cannot be finished while days are pending.');
    }
}
