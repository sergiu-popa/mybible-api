<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a user account has been soft-deleted via the self-service
 * "delete account" endpoint. Primitive payload only so a queued listener can
 * process the cascade long after the soft-deleted row is hard-deleted.
 *
 * TODO: MBA-018 follow-up — a queued listener in a dedicated data-retention
 * story will consume this event to cascade notes, favorites, reading-plan
 * subscriptions, and hymnal/devotional favorites, and to hard-delete the
 * user after a configurable grace period.
 */
final class UserAccountDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {}
}
