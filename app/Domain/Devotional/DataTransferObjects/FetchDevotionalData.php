<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;

final readonly class FetchDevotionalData
{
    public function __construct(
        public Language $language,
        public int $typeId,
        public string $typeSlug,
        public CarbonImmutable $date,
    ) {}
}
