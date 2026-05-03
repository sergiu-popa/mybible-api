<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Models;

use App\Domain\EducationalResources\QueryBuilders\ResourceBookChapterQueryBuilder;
use Database\Factories\ResourceBookChapterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $resource_book_id
 * @property int $position
 * @property string $title
 * @property string $content
 * @property string|null $audio_cdn_url
 * @property string|null $audio_embed
 * @property int|null $duration_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ResourceBook $book
 */
#[UseFactory(ResourceBookChapterFactory::class)]
final class ResourceBookChapter extends Model
{
    /** @use HasFactory<ResourceBookChapterFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'int',
            'duration_seconds' => 'int',
        ];
    }

    /**
     * @return BelongsTo<ResourceBook, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(ResourceBook::class, 'resource_book_id');
    }

    public function hasAudio(): bool
    {
        return $this->audio_cdn_url !== null || $this->audio_embed !== null;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ResourceBookChapterQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ResourceBookChapterQueryBuilder($query);
    }
}
