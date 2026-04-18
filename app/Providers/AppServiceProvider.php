<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Policies\ReadingPlanSubscriptionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(ReadingPlanSubscription::class, ReadingPlanSubscriptionPolicy::class);
    }
}
