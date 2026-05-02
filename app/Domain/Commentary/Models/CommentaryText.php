<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Models;

use App\Domain\Commentary\QueryBuilders\CommentaryTextQueryBuilder;
use Database\Factories\CommentaryTextFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $commentary_id
 * @property string $book
 * @property int $chapter
 * @property int $position
 * @property int|null $verse_from
 * @property int|null $verse_to
 * @property string|null $verse_label
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Commentary $commentary
 */
#[UseFactory(CommentaryTextFactory::class)]
final class CommentaryText extends Model
{
    /** @use HasFactory<CommentaryTextFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chapter' => 'integer',
            'position' => 'integer',
            'verse_from' => 'integer',
            'verse_to' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Commentary, $this>
     */
    public function commentary(): BelongsTo
    {
        return $this->belongsTo(Commentary::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CommentaryTextQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CommentaryTextQueryBuilder($query);
    }
}
