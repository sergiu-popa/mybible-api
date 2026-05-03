<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Events;

use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Models\ResourceDownload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class DownloadOccurred
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventType,
        public readonly Model $subject,
        public readonly ResourceDownloadContextData $context,
        public readonly ResourceDownload $row,
    ) {}
}
