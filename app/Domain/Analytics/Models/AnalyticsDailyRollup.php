<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\QueryBuilders\AnalyticsDailyRollupQueryBuilder;
use Database\Factories\AnalyticsDailyRollupFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property string $event_type
 * @property string $subject_type
 * @property int $subject_id
 * @property string $language
 * @property int $event_count
 * @property int $unique_users
 * @property int $unique_devices
 */
#[UseFactory(AnalyticsDailyRollupFactory::class)]
final class AnalyticsDailyRollup extends Model
{
    /** @use HasFactory<AnalyticsDailyRollupFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'event_count' => 'integer',
            'unique_users' => 'integer',
            'unique_devices' => 'integer',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return AnalyticsDailyRollupQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new AnalyticsDailyRollupQueryBuilder($query);
    }
}
