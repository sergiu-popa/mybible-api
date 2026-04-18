<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;

final class ReadingPlanSubscriptionPolicy
{
    public function manage(User $user, ReadingPlanSubscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }
}
