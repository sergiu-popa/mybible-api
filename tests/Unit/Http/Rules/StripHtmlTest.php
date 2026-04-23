<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Rules;

use App\Http\Rules\StripHtml;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class StripHtmlTest extends TestCase
{
    public function test_it_strips_html_tags_before_downstream_rules_run(): void
    {
        $validator = Validator::make(
            ['content' => '<b>hello</b> <script>alert(1)</script>world'],
            ['content' => ['required', 'string', new StripHtml, 'max:20']],
        );

        $this->assertTrue($validator->passes(), implode(', ', $validator->errors()->all()));
        $this->assertSame('hello alert(1)world', $validator->validated()['content']);
    }

    public function test_it_leaves_plain_text_untouched(): void
    {
        $validator = Validator::make(
            ['content' => 'just text'],
            ['content' => ['required', 'string', new StripHtml, 'max:20']],
        );

        $this->assertTrue($validator->passes());
        $this->assertSame('just text', $validator->validated()['content']);
    }

    public function test_length_is_measured_after_stripping(): void
    {
        // `<b>hi</b>` is 9 chars; after strip_tags it becomes `hi` (2 chars)
        // and the `max:5` check sees 2. The field would fail `max` only if
        // we still saw the pre-strip 9-char value.
        $validator = Validator::make(
            ['content' => '<b>hi</b>'],
            ['content' => ['required', 'string', new StripHtml, 'max:5']],
        );

        $this->assertTrue($validator->passes());
        $this->assertSame('hi', $validator->validated()['content']);
    }

    public function test_length_still_fails_when_stripped_value_exceeds_limit(): void
    {
        $validator = Validator::make(
            ['content' => str_repeat('a', 30) . '<b>hi</b>'],
            ['content' => ['required', 'string', new StripHtml, 'max:10']],
        );

        $this->assertTrue($validator->fails());
    }
}
