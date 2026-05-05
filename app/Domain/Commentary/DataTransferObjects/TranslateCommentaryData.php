<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

final readonly class TranslateCommentaryData
{
    public function __construct(
        public int $sourceCommentaryId,
        public string $targetLanguage,
        public bool $overwrite = false,
        public ?int $triggeredByUserId = null,
    ) {}
}
