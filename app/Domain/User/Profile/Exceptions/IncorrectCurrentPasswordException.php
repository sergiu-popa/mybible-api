<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Thrown from the profile change-password / delete-account Actions when the
 * caller-supplied current password does not match the stored hash.
 *
 * Extends `ValidationException` so the project-wide 422 renderer in
 * `bootstrap/app.php` serializes it using the same JSON envelope as any
 * other validation error — no dedicated render handler required.
 */
final class IncorrectCurrentPasswordException extends ValidationException
{
    /**
     * Seeds the validator's error bag with a field-targeted message, so the
     * 422 JSON response carries `errors.<field> = ["..."]` for the caller.
     *
     * `parent::withMessages()` uses `new static(...)` internally, so late
     * static binding resolves it to this subclass — no manual rebuild needed.
     */
    public static function forField(string $field): self
    {
        return parent::withMessages([
            $field => [__('The provided password is incorrect.')],
        ]);
    }
}
