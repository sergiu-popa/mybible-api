<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Models;

use App\Domain\Commentary\QueryBuilders\CommentaryTextQueryBuilder;
use Database\Factories\CommentaryTextFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $original
 * @property string|null $plain
 * @property string|null $with_references
 * @property int $errors_reported
 * @property Carbon|null $ai_corrected_at
 * @property string|null $ai_corrected_prompt_version
 * @property Carbon|null $ai_referenced_at
 * @property string|null $ai_referenced_prompt_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Commentary $commentary
 * @property-read Collection<int, CommentaryErrorReport> $errorReports
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
            'errors_reported' => 'integer',
            'ai_corrected_at' => 'datetime',
            'ai_referenced_at' => 'datetime',
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
     * @return HasMany<CommentaryErrorReport, $this>
     */
    public function errorReports(): HasMany
    {
        return $this->hasMany(CommentaryErrorReport::class);
    }

    /**
     * Resolved render content per AC §2: prefer the AI-referenced HTML,
     * fall back to plain, then original, then the legacy `content` column.
     */
    public function resolvedContent(): string
    {
        $candidates = [$this->with_references, $this->plain, $this->original, $this->content];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return (string) $this->content;
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
