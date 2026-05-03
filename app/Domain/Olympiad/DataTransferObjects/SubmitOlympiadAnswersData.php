<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

use App\Domain\Olympiad\Models\OlympiadAttempt;

final readonly class SubmitOlympiadAnswersData
{
    /**
     * @param  list<SubmitOlympiadAnswerLine>  $lines
     */
    public function __construct(
        public OlympiadAttempt $attempt,
        public array $lines,
    ) {}
}
