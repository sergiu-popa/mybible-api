<?php

declare(strict_types=1);

namespace App\Domain\Collections\Models;

use App\Domain\Collections\QueryBuilders\CollectionTopicQueryBuilder;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Database\Factories\CollectionTopicFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $language
 * @property string $name
 * @property ?string $description
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CollectionReference> $references
 * @property-read int|null $reference_count
 */
#[UseFactory(CollectionTopicFactory::class)]
final class CollectionTopic extends Model
{
    /** @use HasFactory<CollectionTopicFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<CollectionReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(CollectionReference::class)->orderBy('position');
    }

    /**
     * Restrict route-model binding to topics in the request-resolved language
     * so a cross-language lookup 404s before the controller runs.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $language = request()->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return CollectionTopic::query()
            ->forLanguage($language instanceof Language ? $language : Language::En)
            ->where($field, $value)
            ->first();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CollectionTopicQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CollectionTopicQueryBuilder($query);
    }
}
