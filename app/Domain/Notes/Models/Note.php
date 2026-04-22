<?php

declare(strict_types=1);

namespace App\Domain\Notes\Models;

use App\Domain\Notes\QueryBuilders\NoteQueryBuilder;
use App\Models\User;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reference
 * @property string $book
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[UseFactory(NoteFactory::class)]
final class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return NoteQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new NoteQueryBuilder($query);
    }
}
