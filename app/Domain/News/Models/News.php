<?php

declare(strict_types=1);

namespace App\Domain\News\Models;

use App\Domain\News\QueryBuilders\NewsQueryBuilder;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $language
 * @property string $title
 * @property string $summary
 * @property ?string $content
 * @property ?string $image_url
 * @property ?Carbon $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(NewsFactory::class)]
final class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    protected $table = 'news';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return NewsQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new NewsQueryBuilder($query);
    }
}
