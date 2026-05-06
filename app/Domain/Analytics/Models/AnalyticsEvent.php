<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\QueryBuilders\AnalyticsEventQueryBuilder;
use Database\Factories\AnalyticsEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $user_id
 * @property string|null $device_id
 * @property string|null $language
 * @property string|null $source
 * @property string|null $app_version
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property-read Model|null $subject
 */
#[UseFactory(AnalyticsEventFactory::class)]
final class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo(name: 'subject');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return AnalyticsEventQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new AnalyticsEventQueryBuilder($query);
    }
}
