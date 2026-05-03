<?php

declare(strict_types=1);

namespace App\Domain\Notes\DataTransferObjects;

use App\Domain\Reference\Reference;
use App\Models\User;

final readonly class CreateNoteData
{
    public function __construct(
        public User $user,
        public Reference $reference,
        public string $canonicalReference,
        public string $content,
        public ?string $color,
    ) {}
}
