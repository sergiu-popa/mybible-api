<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;

final readonly class ListDevotionalArchiveData
{
    public function __construct(
        public Language $language,
        public DevotionalType $type,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public int $perPage,
    ) {}
}
