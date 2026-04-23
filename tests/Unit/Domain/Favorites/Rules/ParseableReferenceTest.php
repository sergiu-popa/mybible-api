<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Rules;

use App\Domain\Favorites\Rules\ParseableReference;
use App\Domain\Reference\Parser\ReferenceParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use Tests\TestCase;

final class ParseableReferenceTest extends TestCase
{
    public function test_it_passes_a_single_valid_reference(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 'GEN.1:1-3.VDC', $this->collector($failures));

        $this->assertSame([], $failures);
        $parsed = $rule->parsed();
        $this->assertNotNull($parsed);
        $this->assertSame('GEN', $parsed->book);
    }

    public function test_it_fails_on_multi_reference_input(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 'GEN.1:1.VDC;GEN.1:2.VDC', $this->collector($failures));

        $this->assertNotSame([], $failures);
        $this->assertNull($rule->parsed());
    }

    public function test_it_fails_on_chapter_range(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 'GEN.1-3.VDC', $this->collector($failures));

        $this->assertNotSame([], $failures);
        $this->assertNull($rule->parsed());
    }

    public function test_it_fails_on_invalid_book(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 'XYZ.1:1.VDC', $this->collector($failures));

        $this->assertNotSame([], $failures);
        $this->assertNull($rule->parsed());
    }

    public function test_it_fails_when_version_is_missing(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 'GEN.1:1.', $this->collector($failures));

        $this->assertNotSame([], $failures);
        $this->assertNull($rule->parsed());
    }

    public function test_it_fails_on_non_string_input(): void
    {
        $rule = new ParseableReference(new ReferenceParser);

        $failures = [];
        $rule->validate('reference', 123, $this->collector($failures));

        $this->assertNotSame([], $failures);
    }

    /**
     * Build a Closure matching the {@see ValidationRule::validate()}
     * fail-callback signature that collects every failure message into the
     * given array.
     *
     * @param  array<int, string>  $failures
     * @return Closure(string, ?string=): PotentiallyTranslatedString
     */
    private function collector(array &$failures): Closure
    {
        $translator = new Translator(new ArrayLoader, 'en');

        return function (string $message, ?string $attribute = null) use (&$failures, $translator): PotentiallyTranslatedString {
            $failures[] = $message;

            return new PotentiallyTranslatedString($message, $translator);
        };
    }
}
