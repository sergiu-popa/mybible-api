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
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        self::saving(function (self $fragment): void {
            $fragment->assertContentMatchesType();
        });
    }

    /**
     * @return BelongsTo<ReadingPlanDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(ReadingPlanDay::class);
    }

    /**
     * Guards against silent content/type mismatches: References must be a list
     * of non-empty strings; Html must be a locale => string map.
     */
    private function assertContentMatchesType(): void
    {
        $content = $this->content;

        match ($this->type) {
            FragmentType::References => $this->assertReferencesContent($content),
            FragmentType::Html => $this->assertHtmlContent($content),
        };
    }

    /**
     * @param  array<array-key, mixed>  $content
     */
    private function assertReferencesContent(array $content): void
    {
        if (! array_is_list($content)) {
            throw new InvalidArgumentException('References fragment content must be a list.');
        }
        foreach ($content as $item) {
            if (! is_string($item) || $item === '') {
                throw new InvalidArgumentException('References fragment entries must be non-empty strings.');
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $content
     */
    private function assertHtmlContent(array $content): void
    {
        if ($content === [] || array_is_list($content)) {
            throw new InvalidArgumentException('Html fragment content must be a locale-keyed map.');
        }
        foreach ($content as $locale => $value) {
            if (! is_string($locale) || $locale === '' || ! is_string($value)) {
                throw new InvalidArgumentException('Html fragment content must map string locales to string values.');
            }
        }
    }
}
