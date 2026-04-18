<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

final class FinishReadingPlanSubscriptionRequest extends AuthorizedReadingPlanSubscriptionRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
