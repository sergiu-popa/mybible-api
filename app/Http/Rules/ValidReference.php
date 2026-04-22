<?php

declare(strict_types=1);

namespace App\Http\Rules;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

/**
 * Validation rule that parses the attribute value via {@see ReferenceParser}.
 *
 * On success the first parsed {@see Reference} is stashed on the current
 * request's attribute bag under {@see self::PARSED_ATTRIBUTE_KEY}, so the
 * Form Request's `toData()` can re-use it without parsing twice.
 */
final class ValidReference implements ValidationRule
{
    public const PARSED_ATTRIBUTE_KEY = 'notes.parsed_reference';

    public function __construct(
        private readonly ReferenceParser $parser,
        private readonly Request $request,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a non-empty string.');

            return;
        }

        try {
            $references = $this->parser->parse($value);
        } catch (InvalidReferenceException $e) {
            $fail($e->reason());

            return;
        }

        if ($references === []) {
            $fail('The :attribute could not be parsed.');

            return;
        }

        // Store the first parsed Reference so the Form Request can recover
        // it without re-parsing.
        $this->request->attributes->set(self::PARSED_ATTRIBUTE_KEY, $references[0]);
    }
}
