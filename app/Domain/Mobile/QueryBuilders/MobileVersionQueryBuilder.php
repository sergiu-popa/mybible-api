<?php

declare(strict_types=1);

namespace App\Domain\Mobile\QueryBuilders;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<MobileVersion>
 */
final class MobileVersionQueryBuilder extends Builder
{
    public function forPlatform(string $platform): self
    {
        return $this->where('platform', $platform);
    }

    public function ofKind(string $kind): self
    {
        return $this->where('kind', $kind);
    }
}
