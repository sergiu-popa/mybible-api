<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Models;

use App\Domain\Olympiad\QueryBuilders\OlympiadAttemptQueryBuilder;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Database\Factories\OlympiadAttemptFactory;
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
 * @property int $user_id
 * @property string $book
 * @property string $chapters_label
 * @property Language $language
 * @property int $score
 * @property int $total
 * @property Carbon $started_at
 * @property ?Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, OlympiadAttemptAnswer> $answers
 */
#[UseFactory(OlympiadAttemptFactory::class)]
final class OlympiadAttempt extends Model
{
    /** @use HasFactory<OlympiadAttemptFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'language' => Language::class,
        ];
    }

    /**
     * Scope route-model binding to the authenticated user — a stale id from a
     * different user 404s rather than 403s/leaking other users' attempts.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();
        $userId = auth()->id();

        if ($userId === null) {
            return null;
        }

        return self::query()
            ->where('user_id', $userId)
            ->where($field, $value)
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OlympiadAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(OlympiadAttemptAnswer::class, 'attempt_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return OlympiadAttemptQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new OlympiadAttemptQueryBuilder($query);
    }
}
