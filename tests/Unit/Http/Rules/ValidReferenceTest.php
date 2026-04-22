<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Rules;

use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Http\Rules\ValidReference;
use Closure;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Translation\PotentiallyTranslatedString;
use Tests\TestCase;

final class ValidReferenceTest extends TestCase
{
    public function test_it_passes_for_a_canonical_reference_and_stashes_parsed_reference(): void
    {
        $request = Request::create('/');
        $rule = new ValidReference(new ReferenceParser, $request);

        $messages = [];
        $rule->validate('reference', 'GEN.1:1.VDC', $this->failCollector($messages));

        $this->assertSame([], $messages);
        $stashed = $request->attributes->get(ValidReference::PARSED_ATTRIBUTE_KEY);
        $this->assertInstanceOf(Reference::class, $stashed);
        $this->assertSame('GEN', $stashed->book);
        $this->assertSame(1, $stashed->chapter);
        $this->assertSame([1], $stashed->verses);
        $this->assertSame('VDC', $stashed->version);
    }

    public function test_it_fails_for_gibberish(): void
    {
        $request = Request::create('/');
        $rule = new ValidReference(new ReferenceParser, $request);

        $messages = [];
        $rule->validate('reference', 'not a real reference', $this->failCollector($messages));

        $this->assertCount(1, $messages);
        $this->assertNotSame('', $messages[0]);
        $this->assertNull($request->attributes->get(ValidReference::PARSED_ATTRIBUTE_KEY));
    }

    public function test_it_fails_for_unknown_book(): void
    {
        $request = Request::create('/');
        $rule = new ValidReference(new ReferenceParser, $request);

        $messages = [];
        $rule->validate('reference', 'XXX.1:1.VDC', $this->failCollector($messages));

        $this->assertCount(1, $messages);
    }

    public function test_it_fails_for_empty_value(): void
    {
        $request = Request::create('/');
        $rule = new ValidReference(new ReferenceParser, $request);

        $messages = [];
        $rule->validate('reference', '', $this->failCollector($messages));

        $this->assertCount(1, $messages);
    }

    /**
     * Produce a closure whose signature satisfies Laravel's $fail contract
     * while capturing every message into the provided array for assertions.
     *
     * @param  array<int, string>  $sink
     * @return Closure(string, string|null=): PotentiallyTranslatedString
     */
    private function failCollector(array &$sink): Closure
    {
        /** @var Translator $translator */
        $translator = $this->app->make('translator');

        return function (string $message, ?string $attribute = null) use (&$sink, $translator): PotentiallyTranslatedString {
            $sink[] = $message;

            return new PotentiallyTranslatedString($message, $translator);
        };
    }
}
