<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Models;

use App\Domain\ReadingPlans\Enums\FragmentType;
use Database\Factories\ReadingPlanDayFragmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reading_plan_day_id
 * @property int $position
 * @property FragmentType $type
 * @property array<array-key, mixed> $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ReadingPlanDay $day
 */
#[UseFactory(ReadingPlanDayFragmentFactory::class)]
final class ReadingPlanDayFragment extends Model
{
    /** @use HasFactory<ReadingPlanDayFragmentFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FragmentType::class,
            'content' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ReadingPlanDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(ReadingPlanDay::class, 'reading_plan_day_id');
    }
}
