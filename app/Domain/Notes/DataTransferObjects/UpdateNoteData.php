<?php

declare(strict_types=1);

namespace App\Domain\Notes\DataTransferObjects;

use App\Domain\Notes\Models\Note;

final readonly class UpdateNoteData
{
    public function __construct(
        public Note $note,
        public string $content,
    ) {}
}
