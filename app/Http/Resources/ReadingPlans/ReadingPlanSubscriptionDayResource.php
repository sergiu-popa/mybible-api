<?php

declare(strict_types=1);

namespace App\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingPlanSubscriptionDay
 */
final class ReadingPlanSubscriptionDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_id' => $this->reading_plan_day_id,
            'position' => $this->whenLoaded('readingPlanDay', fn () => $this->readingPlanDay->position),
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
