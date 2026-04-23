<?php

declare(strict_types=1);

namespace App\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Transform rule that rewrites the attribute's value to `strip_tags($value)`
 * on the validator's data. Place *before* length rules so downstream rules
 * (including `max:N`) see the stripped value.
 *
 * This is a client-compat courtesy, not a security boundary — the server
 * never renders note content as HTML.
 */
final class StripHtml implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->validator === null) {
            return;
        }

        $stripped = strip_tags($value);

        if ($stripped === $value) {
            return;
        }

        $this->validator->setValue($attribute, $stripped);
    }
}
