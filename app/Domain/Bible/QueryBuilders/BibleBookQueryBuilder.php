<?php

declare(strict_types=1);

namespace App\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Models\BibleBook;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<BibleBook>
 */
final class BibleBookQueryBuilder extends Builder
{
    public function inCanonicalOrder(): self
    {
        return $this->orderBy('position');
    }
}
