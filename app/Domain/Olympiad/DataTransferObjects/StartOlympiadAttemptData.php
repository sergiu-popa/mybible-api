<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use App\Models\User;

final readonly class StartOlympiadAttemptData
{
    public function __construct(
        public User $user,
        public string $book,
        public ChapterRange $range,
        public Language $language,
    ) {}
}
