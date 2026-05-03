<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Support;

/**
 * Allowed `sabbath_school_segment_contents.type` values.
 *
 * Derived from Symfony usage. New types are introduced by adding a case
 * here and Form Requests pick them up via `values()`.
 */
enum SegmentContentType: string
{
    case Text = 'text';
    case Question = 'question';
    case MemoryVerse = 'memory_verse';
    case Passage = 'passage';
    case Prayer = 'prayer';
    case Discussion = 'discussion';
    case Summary = 'summary';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
