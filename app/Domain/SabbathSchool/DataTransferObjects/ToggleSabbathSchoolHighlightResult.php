<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;

final readonly class ToggleSabbathSchoolHighlightResult
{
    public function __construct(
        public ?SabbathSchoolHighlight $highlight,
        public bool $created,
    ) {}

    public static function created(SabbathSchoolHighlight $highlight): self
    {
        return new self($highlight, true);
    }

    public static function deleted(): self
    {
        return new self(null, false);
    }
}
