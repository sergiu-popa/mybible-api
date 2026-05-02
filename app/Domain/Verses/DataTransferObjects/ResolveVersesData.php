<?php

declare(strict_types=1);

namespace App\Domain\Verses\DataTransferObjects;

use App\Domain\Reference\Reference;
use App\Domain\Reference\VerseRange;

final readonly class ResolveVersesData
{
    /**
     * @param  array<int, Reference|VerseRange>  $references  Every entry carries a non-null `$version`.
     */
    public function __construct(
        public array $references,
        public string $version,
    ) {}
}
