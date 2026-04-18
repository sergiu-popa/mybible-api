<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
