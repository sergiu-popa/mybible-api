<?php

declare(strict_types=1);

namespace App\Domain\Verses\DataTransferObjects;

use App\Domain\Bible\Models\BibleVerse;
use Illuminate\Database\Eloquent\Collection;

final readonly class VerseLookupResult
{
    /**
     * @param  Collection<int, BibleVerse>  $verses
     * @param  array<int, array{version: string, book: string, chapter: int, verse: int}>  $missing
     */
    public function __construct(
        public Collection $verses,
        public array $missing,
    ) {}
}
