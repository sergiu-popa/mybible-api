<?php

declare(strict_types=1);

namespace App\Domain\Bible\Models;

use App\Domain\Bible\QueryBuilders\BibleBookQueryBuilder;
use Database\Factories\BibleBookFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $abbreviation
 * @property string $testament
 * @property int $position
 * @property int $chapter_count
 * @property array<string, string> $names
 * @property array<string, string> $short_names
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, BibleChapter> $chapters
 */
#[UseFactory(BibleBookFactory::class)]
final class BibleBook extends Model
{
    /** @use HasFactory<BibleBookFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'names' => 'array',
            'short_names' => 'array',
            'position' => 'integer',
            'chapter_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'abbreviation';
    }

    /**
     * @return HasMany<BibleChapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(BibleChapter::class)->orderBy('number');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return BibleBookQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new BibleBookQueryBuilder($query);
    }
}
