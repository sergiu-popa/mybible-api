<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\QueryBuilders\ResourceDownloadQueryBuilder;
use Database\Factories\ResourceDownloadFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $downloadable_type
 * @property int $downloadable_id
 * @property int|null $user_id
 * @property string|null $device_id
 * @property string|null $language
 * @property string|null $source
 * @property Carbon $created_at
 * @property-read Model $downloadable
 */
#[UseFactory(ResourceDownloadFactory::class)]
final class ResourceDownload extends Model
{
    /** @use HasFactory<ResourceDownloadFactory> */
    use HasFactory;

    public const TYPE_EDUCATIONAL_RESOURCE = 'educational_resource';

    public const TYPE_RESOURCE_BOOK = 'resource_book';

    public const TYPE_RESOURCE_BOOK_CHAPTER = 'resource_book_chapter';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function downloadable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return ResourceDownloadQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ResourceDownloadQueryBuilder($query);
    }
}
