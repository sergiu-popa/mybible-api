<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Rules;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a canonical reference string (MBA-006) and memoizes the parsed
 * {@see Reference} so the FormRequest's `toData()` can reuse it without a
 * second parse. Multi-reference inputs (chapter ranges or `;`-separated
 * lists) are rejected — one favorite per POST per AC 6.
 */
final class ParseableReference implements ValidationRule
{
    private ?Reference $parsed = null;

    public function __construct(
        private readonly ReferenceParser $parser,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a non-empty canonical reference string.');

            return;
        }

        try {
            $references = $this->parser->parse($value);
        } catch (InvalidReferenceException) {
            $fail('The :attribute is not a valid reference.');

            return;
        }

        if (count($references) !== 1) {
            $fail('The :attribute must refer to a single passage.');

            return;
        }

        $reference = $references[0];

        if ($reference->version === null) {
            $fail('The :attribute must include a Bible version.');

            return;
        }

        $this->parsed = $reference;
    }

    public function parsed(): ?Reference
    {
        return $this->parsed;
    }
}
