<?php

declare(strict_types=1);

namespace App\Domain\Verses\Exceptions;

use DateTimeImmutable;
use RuntimeException;

final class NoDailyVerseForDateException extends RuntimeException
{
    public function __construct(
        public readonly DateTimeImmutable $date,
    ) {
        parent::__construct('No daily verse for this date.');
    }
}
