<?php

declare(strict_types=1);

namespace App\Domain\Verses\Actions;

use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Domain\Verses\Models\DailyVerse;
use DateTimeImmutable;

final class GetDailyVerseAction
{
    public function handle(DateTimeImmutable $date): DailyVerse
    {
        $verse = DailyVerse::query()->forDate($date);

        if ($verse === null) {
            throw new NoDailyVerseForDateException($date);
        }

        return $verse;
    }
}
