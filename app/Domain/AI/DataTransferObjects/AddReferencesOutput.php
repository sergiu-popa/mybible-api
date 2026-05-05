<?php

declare(strict_types=1);

namespace App\Domain\AI\DataTransferObjects;

final readonly class AddReferencesOutput
{
    public function __construct(
        public string $html,
        public int $referencesAdded,
        public string $promptVersion,
        public int $aiCallId,
    ) {}
}
