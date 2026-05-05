<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

use App\Domain\Commentary\Models\CommentaryText;

final readonly class AICorrectCommentaryTextData
{
    public function __construct(
        public CommentaryText $text,
        public ?int $triggeredByUserId = null,
    ) {}
}
