<?php

declare(strict_types=1);

namespace App\Domain\QrCode\QueryBuilders;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<QrCode>
 */
final class QrCodeQueryBuilder extends Builder
{
    public function forReference(string $canonical): self
    {
        return $this->whereNotNull('reference')->where('reference', $canonical);
    }
}
