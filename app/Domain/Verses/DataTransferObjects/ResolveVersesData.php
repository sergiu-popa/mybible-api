<?php

declare(strict_types=1);

namespace App\Domain\Verses\DataTransferObjects;

use App\Domain\Reference\Reference;

final readonly class ResolveVersesData
{
    /**
     * @param  array<int, Reference>  $references  Every reference carries a non-null `$version`.
     */
    public function __construct(
        public array $references,
        public string $version,
    ) {}
}
