<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\DataTransferObjects;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Carbon\CarbonImmutable;

final readonly class RescheduleReadingPlanSubscriptionData
{
    public function __construct(
        public ReadingPlanSubscription $subscription,
        public CarbonImmutable $startDate,
    ) {}
}
