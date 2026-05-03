<?php

declare(strict_types=1);

namespace App\Domain\Analytics\QueryBuilders;

use App\Domain\Analytics\Models\ResourceDownload;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Builder<ResourceDownload>
 */
final class ResourceDownloadQueryBuilder extends Builder
{
    public function for(Model $downloadable): self
    {
        return $this
            ->where('downloadable_type', Relation::getMorphAlias($downloadable::class))
            ->where('downloadable_id', $downloadable->getKey());
    }

    public function countsByDay(CarbonInterface $from, CarbonInterface $to): self
    {
        return $this
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day');
    }
}
