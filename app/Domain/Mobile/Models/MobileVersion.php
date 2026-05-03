<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Models;

use App\Domain\Mobile\QueryBuilders\MobileVersionQueryBuilder;
use Database\Factories\MobileVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $platform
 * @property string $kind
 * @property string $version
 * @property ?Carbon $released_at
 * @property ?array<string, mixed> $release_notes
 * @property ?string $store_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(MobileVersionFactory::class)]
final class MobileVersion extends Model
{
    /** @use HasFactory<MobileVersionFactory> */
    use HasFactory;

    public const KIND_MIN_REQUIRED = 'min_required';

    public const KIND_LATEST = 'latest';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'release_notes' => 'array',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return MobileVersionQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new MobileVersionQueryBuilder($query);
    }
}
