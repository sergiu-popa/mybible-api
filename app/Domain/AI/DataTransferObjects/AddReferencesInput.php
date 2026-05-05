<?php

declare(strict_types=1);

namespace App\Domain\AI\DataTransferObjects;

final readonly class AddReferencesInput
{
    public function __construct(
        public string $html,
        public string $language,
        public ?string $bibleVersionAbbreviation = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?int $triggeredByUserId = null,
    ) {}
}
