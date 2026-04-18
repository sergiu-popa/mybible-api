<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Models;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\QueryBuilders\ReadingPlanQueryBuilder;
use Database\Factories\ReadingPlanFactory;
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
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, string> $image
 * @property array<string, string> $thumbnail
 * @property ReadingPlanStatus $status
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ReadingPlanDay> $days
 * @property-read Collection<int, ReadingPlanSubscription> $subscriptions
 */
#[UseFactory(ReadingPlanFactory::class)]
final class ReadingPlan extends Model
{
    /** @use HasFactory<ReadingPlanFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'image' => 'array',
            'thumbnail' => 'array',
            'status' => ReadingPlanStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ReadingPlanDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(ReadingPlanDay::class)->orderBy('position');
    }

    /**
     * @return HasMany<ReadingPlanSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ReadingPlanSubscription::class);
    }

    /**
     * Restrict route-model binding to published plans so draft/unpublished
     * slugs 404 before controllers ever see them.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        return ReadingPlan::query()
            ->published()
            ->where($field, $value)
            ->first();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ReadingPlanQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ReadingPlanQueryBuilder($query);
    }
}
