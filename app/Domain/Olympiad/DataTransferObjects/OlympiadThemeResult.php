<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Support\Collection;

final readonly class OlympiadThemeResult
{
    /**
     * @param  Collection<int, OlympiadQuestion>  $questions
     */
    public function __construct(
        public Collection $questions,
        public int $seed,
    ) {}
}
