<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Enums;

enum CommentaryErrorReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Fixed = 'fixed';
    case Dismissed = 'dismissed';

    /**
     * Whether the report still counts toward the open `errors_reported`
     * counter on the parent `CommentaryText`.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::Reviewed => true,
            self::Fixed, self::Dismissed => false,
        };
    }

    /**
     * Counter delta when transitioning from `$this` to `$target`.
     * Positive transitions (closed → open) yield `+1`; closing
     * transitions yield `-1`. The action clamps the resulting value
     * to a 0 floor.
     */
    public function counterDelta(self $target): int
    {
        if ($this->isOpen() === $target->isOpen()) {
            return 0;
        }

        return $target->isOpen() ? 1 : -1;
    }
}
