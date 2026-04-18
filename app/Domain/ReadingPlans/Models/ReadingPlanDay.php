<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Models;

use Database\Factories\ReadingPlanDayFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reading_plan_id
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ReadingPlan $readingPlan
 * @property-read Collection<int, ReadingPlanDayFragment> $fragments
 */
#[UseFactory(ReadingPlanDayFactory::class)]
final class ReadingPlanDay extends Model
{
    /** @use HasFactory<ReadingPlanDayFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<ReadingPlan, $this>
     */
    public function readingPlan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class);
    }

    /**
     * @return HasMany<ReadingPlanDayFragment, $this>
     */
    public function fragments(): HasMany
    {
        return $this->hasMany(ReadingPlanDayFragment::class)->orderBy('position');
    }
}
