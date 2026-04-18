<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\ReadingPlans\DataTransferObjects\RescheduleReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class RescheduleReadingPlanSubscriptionRequest extends FormRequest
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
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function toData(ReadingPlanSubscription $subscription): RescheduleReadingPlanSubscriptionData
    {
        /** @var string $startDate */
        $startDate = $this->validated('start_date');

        return new RescheduleReadingPlanSubscriptionData(
            subscription: $subscription,
            startDate: CarbonImmutable::parse($startDate),
        );
    }
}
