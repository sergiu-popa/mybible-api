<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Models;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\QueryBuilders\EducationalResourceQueryBuilder;
use Database\Factories\EducationalResourceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $resource_category_id
 * @property ResourceType $type
 * @property array<string, string> $title
 * @property array<string, string>|null $summary
 * @property array<string, string> $content
 * @property ?string $thumbnail_path
 * @property ?string $media_path
 * @property ?string $author
 * @property Carbon $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ResourceCategory $category
 */
#[UseFactory(EducationalResourceFactory::class)]
final class EducationalResource extends Model
{
    /** @use HasFactory<EducationalResourceFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Public identity. Integer `id` stays as FK target.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'title' => 'array',
            'summary' => 'array',
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ResourceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return EducationalResourceQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new EducationalResourceQueryBuilder($query);
    }
}
