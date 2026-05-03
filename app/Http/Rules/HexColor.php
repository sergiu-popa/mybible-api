<?php

declare(strict_types=1);

namespace App\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a CSS hex colour: `#RRGGBB` or `#RRGGBBAA` (case-insensitive).
 */
final class HexColor implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $value) !== 1) {
            $fail('The :attribute must be a hex colour in the form #RRGGBB or #RRGGBBAA.');
        }
    }
}
