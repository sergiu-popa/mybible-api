<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

final readonly class SubmitOlympiadAnswerLine
{
    public function __construct(
        public string $questionUuid,
        public ?string $selectedAnswerUuid,
    ) {}
}
