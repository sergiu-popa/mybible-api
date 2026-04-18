<?php

declare(strict_types=1);

namespace App\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingPlanSubscription
 */
final class ReadingPlanSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->reading_plan_id,
            'status' => $this->status->value,
            'start_date' => $this->start_date->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'progress' => [
                'completed_days' => $this->completedDaysCount(),
                'total_days' => $this->totalDaysCount(),
            ],
            'days' => ReadingPlanSubscriptionDayResource::collection($this->whenLoaded('days')),
        ];
    }

    private function completedDaysCount(): int
    {
        if ($this->completed_days_count !== null) {
            return $this->completed_days_count;
        }

        if ($this->relationLoaded('days')) {
            return $this->days->whereNotNull('completed_at')->count();
        }

        return 0;
    }

    private function totalDaysCount(): int
    {
        if ($this->days_count !== null) {
            return $this->days_count;
        }

        if ($this->relationLoaded('days')) {
            return $this->days->count();
        }

        return 0;
    }
}
