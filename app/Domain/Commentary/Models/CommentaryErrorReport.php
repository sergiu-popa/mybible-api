<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Models;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\QueryBuilders\CommentaryErrorReportQueryBuilder;
use App\Models\User;
use Database\Factories\CommentaryErrorReportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $commentary_text_id
 * @property int|null $user_id
 * @property string|null $device_id
 * @property string $book
 * @property int $chapter
 * @property int|null $verse
 * @property string $description
 * @property CommentaryErrorReportStatus $status
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommentaryText $commentaryText
 * @property-read User|null $user
 * @property-read User|null $reviewer
 */
#[UseFactory(CommentaryErrorReportFactory::class)]
final class CommentaryErrorReport extends Model
{
    /** @use HasFactory<CommentaryErrorReportFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommentaryErrorReportStatus::class,
            'reviewed_at' => 'datetime',
            'chapter' => 'integer',
            'verse' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommentaryText, $this>
     */
    public function commentaryText(): BelongsTo
    {
        return $this->belongsTo(CommentaryText::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CommentaryErrorReportQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CommentaryErrorReportQueryBuilder($query);
    }
}
