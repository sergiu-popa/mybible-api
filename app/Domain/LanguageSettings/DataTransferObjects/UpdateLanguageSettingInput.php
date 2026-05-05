<?php

declare(strict_types=1);

namespace App\Domain\LanguageSettings\DataTransferObjects;

final readonly class UpdateLanguageSettingInput
{
    public function __construct(
        public string $language,
        public bool $bibleVersionProvided,
        public ?string $defaultBibleVersionAbbreviation,
        public bool $commentaryProvided,
        public ?int $defaultCommentaryId,
        public bool $devotionalTypeProvided,
        public ?int $defaultDevotionalTypeId,
    ) {}
}
