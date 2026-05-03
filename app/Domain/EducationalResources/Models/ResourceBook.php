<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Models;

use App\Domain\EducationalResources\QueryBuilders\ResourceBookQueryBuilder;
use Database\Factories\ResourceBookFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $language
 * @property string|null $description
 * @property int $position
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property string|null $cover_image_url
 * @property string|null $author
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ResourceBookChapter> $chapters
 */
#[UseFactory(ResourceBookFactory::class)]
final class ResourceBook extends Model
{
    /** @use HasFactory<ResourceBookFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'int',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Public `{book:slug}` routes hide drafts; admin `{book}` (defaults
     * to `id`) routes serve drafts so super-admins can edit them.
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
     * @return HasMany<ResourceBookChapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(ResourceBookChapter::class)->orderBy('position');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ResourceBookQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ResourceBookQueryBuilder($query);
    }
}
