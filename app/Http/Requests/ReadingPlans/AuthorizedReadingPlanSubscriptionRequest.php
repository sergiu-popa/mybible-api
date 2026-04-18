<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedReadingPlanSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        $user = $this->user();

        if (! $subscription instanceof ReadingPlanSubscription || ! $user instanceof User) {
            return false;
        }

        return $user->can('manage', $subscription);
    }
}
