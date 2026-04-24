<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Models;

use App\Domain\EducationalResources\QueryBuilders\ResourceCategoryQueryBuilder;
use Database\Factories\ResourceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property string $language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, EducationalResource> $resources
 * @property-read int|null $resource_count
 */
#[UseFactory(ResourceCategoryFactory::class)]
final class ResourceCategory extends Model
{
    /** @use HasFactory<ResourceCategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
        ];
    }

    /**
     * @return HasMany<EducationalResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(EducationalResource::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ResourceCategoryQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ResourceCategoryQueryBuilder($query);
    }
}
