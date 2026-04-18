<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\DataTransferObjects;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class StartReadingPlanSubscriptionData
{
    public function __construct(
        public User $user,
        public ReadingPlan $plan,
        public CarbonImmutable $startDate,
    ) {}
}
