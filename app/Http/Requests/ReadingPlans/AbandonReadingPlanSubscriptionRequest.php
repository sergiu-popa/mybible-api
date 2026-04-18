<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AbandonReadingPlanSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        $user = $this->user();

        if (! $subscription instanceof ReadingPlanSubscription) {
            return false;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $subscription->user_id === $user->id;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
