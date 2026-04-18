<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Models;

use Database\Factories\ReadingPlanSubscriptionDayFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reading_plan_subscription_id
 * @property int $reading_plan_day_id
 * @property Carbon $scheduled_date
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ReadingPlanSubscription $subscription
 * @property-read ReadingPlanDay $readingPlanDay
 */
#[UseFactory(ReadingPlanSubscriptionDayFactory::class)]
final class ReadingPlanSubscriptionDay extends Model
{
    /** @use HasFactory<ReadingPlanSubscriptionDayFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReadingPlanSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ReadingPlanSubscription::class, 'reading_plan_subscription_id');
    }

    /**
     * @return BelongsTo<ReadingPlanDay, $this>
     */
    public function readingPlanDay(): BelongsTo
    {
        return $this->belongsTo(ReadingPlanDay::class);
    }
}
