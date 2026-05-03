<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Models;

use App\Domain\Olympiad\QueryBuilders\OlympiadQuestionQueryBuilder;
use App\Domain\Shared\Enums\Language;
use Database\Factories\OlympiadQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $book
 * @property ?int $chapters_from
 * @property ?int $chapters_to
 * @property ?int $chapter
 * @property ?int $verse
 * @property Language $language
 * @property string $question
 * @property string|null $explanation
 * @property bool $is_reviewed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, OlympiadAnswer> $answers
 */
#[UseFactory(OlympiadQuestionFactory::class)]
final class OlympiadQuestion extends Model
{
    /** @use HasFactory<OlympiadQuestionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chapters_from' => 'integer',
            'chapters_to' => 'integer',
            'chapter' => 'integer',
            'verse' => 'integer',
            'is_reviewed' => 'boolean',
            'language' => Language::class,
        ];
    }

    /**
     * @return HasMany<OlympiadAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(OlympiadAnswer::class)->orderBy('position');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return OlympiadQuestionQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new OlympiadQuestionQueryBuilder($query);
    }
}
