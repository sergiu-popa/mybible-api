<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Models;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\QueryBuilders\ReadingPlanSubscriptionQueryBuilder;
use App\Models\User;
use Database\Factories\ReadingPlanSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reading_plan_id
 * @property Carbon $start_date
 * @property SubscriptionStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read ReadingPlan $readingPlan
 * @property-read Collection<int, ReadingPlanSubscriptionDay> $days
 * @property int|null $days_count
 * @property int|null $completed_days_count
 */
#[UseFactory(ReadingPlanSubscriptionFactory::class)]
final class ReadingPlanSubscription extends Model
{
    /** @use HasFactory<ReadingPlanSubscriptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'completed_at' => 'datetime',
            'status' => SubscriptionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ReadingPlan, $this>
     */
    public function readingPlan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class);
    }

    /**
     * @return HasMany<ReadingPlanSubscriptionDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(ReadingPlanSubscriptionDay::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ReadingPlanSubscriptionQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ReadingPlanSubscriptionQueryBuilder($query);
    }
}
