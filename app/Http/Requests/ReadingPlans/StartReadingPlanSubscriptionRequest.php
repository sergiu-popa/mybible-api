<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\ReadingPlans\DataTransferObjects\StartReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StartReadingPlanSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function startDate(): CarbonImmutable
    {
        $value = $this->validated('start_date');

        if (! is_string($value) || $value === '') {
            return CarbonImmutable::now()->startOfDay();
        }

        return CarbonImmutable::parse($value);
    }

    public function toData(ReadingPlan $plan): StartReadingPlanSubscriptionData
    {
        /** @var User $user */
        $user = $this->user();

        return new StartReadingPlanSubscriptionData(
            user: $user,
            plan: $plan,
            startDate: $this->startDate(),
        );
    }
}
