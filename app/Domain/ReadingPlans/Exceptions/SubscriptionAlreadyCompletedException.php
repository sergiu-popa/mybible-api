<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class SubscriptionAlreadyCompletedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'Cannot abandon a completed subscription.');
    }
}
