<?php

declare(strict_types=1);

namespace App\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingPlanDay
 */
final class ReadingPlanDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'fragments' => ReadingPlanDayFragmentResource::collection($this->fragments),
        ];
    }
}
