<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;

final readonly class OlympiadThemeRequest
{
    public function __construct(
        public string $book,
        public ChapterRange $range,
        public Language $language,
        public ?int $seed = null,
    ) {}
}
