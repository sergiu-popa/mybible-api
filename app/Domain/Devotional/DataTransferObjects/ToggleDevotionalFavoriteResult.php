<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

use App\Domain\Devotional\Models\DevotionalFavorite;

final readonly class ToggleDevotionalFavoriteResult
{
    public function __construct(
        public bool $created,
        public ?DevotionalFavorite $favorite,
    ) {}

    /**
     * Named `created` to match the HTTP verb ("created") the caller returns.
     * Distinct from the `$created` boolean property despite the visual overlap —
     * PHP resolves static-method and instance-property names independently.
     */
    public static function created(DevotionalFavorite $favorite): self
    {
        return new self(true, $favorite);
    }

    public static function deleted(): self
    {
        return new self(false, null);
    }
}
