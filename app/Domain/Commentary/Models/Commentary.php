<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Models;

use App\Domain\Commentary\QueryBuilders\CommentaryQueryBuilder;
use Database\Factories\CommentaryFactory;
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
 * @property string $slug
 * @property array<string, string> $name
 * @property string $abbreviation
 * @property string $language
 * @property bool $is_published
 * @property int|null $source_commentary_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Commentary|null $sourceCommentary
 * @property-read Collection<int, CommentaryText> $texts
 */
#[UseFactory(CommentaryFactory::class)]
final class Commentary extends Model
{
    /** @use HasFactory<CommentaryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Public `{commentary:slug}` routes hide drafts; admin `{commentary}`
     * (defaults to `id`) routes serve drafts so super-admins can edit them.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = self::query()->where($field, $value);

        if ($field === 'slug') {
            $query->published();
        }

        return $query->first();
    }

    /**
     * @return BelongsTo<Commentary, $this>
     */
    public function sourceCommentary(): BelongsTo
    {
        return $this->belongsTo(Commentary::class, 'source_commentary_id');
    }

    /**
     * @return HasMany<CommentaryText, $this>
     */
    public function texts(): HasMany
    {
        return $this->hasMany(CommentaryText::class)->orderBy('position');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CommentaryQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CommentaryQueryBuilder($query);
    }
}
