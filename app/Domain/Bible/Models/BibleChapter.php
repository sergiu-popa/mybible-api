<?php

declare(strict_types=1);

namespace App\Domain\Bible\Models;

use Database\Factories\BibleChapterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bible_book_id
 * @property int $number
 * @property int $verse_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BibleBook $book
 */
#[UseFactory(BibleChapterFactory::class)]
final class BibleChapter extends Model
{
    /** @use HasFactory<BibleChapterFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'verse_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BibleBook, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }
}
