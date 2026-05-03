<?php

declare(strict_types=1);

namespace App\Domain\Collections\Models;

use App\Domain\Collections\QueryBuilders\CollectionTopicQueryBuilder;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Database\Factories\CollectionTopicFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ?int $collection_id
 * @property string $language
 * @property string $name
 * @property ?string $description
 * @property ?string $image_cdn_url
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?Collection $collection
 * @property-read EloquentCollection<int, CollectionReference> $references
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
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * Restrict route-model binding to topics in the request-resolved language
     * so a cross-language lookup 404s before the controller runs.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $language = $this->resolveLanguageForBinding();

        return CollectionTopic::query()
            ->forLanguage($language)
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

    /**
     * Resolve the request language for route-model binding.
     *
     * Binding can execute before `ResolveRequestLanguage` has populated the
     * request attributes, so we read the raw query string as the middleware
     * does and fall back to the attribute bag when already populated.
     */
    private function resolveLanguageForBinding(): Language
    {
        $request = request();
        $attribute = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY);

        if ($attribute instanceof Language) {
            return $attribute;
        }

        $raw = $request->query('language');

        return Language::fromRequest(is_string($raw) ? $raw : null);
    }
}
