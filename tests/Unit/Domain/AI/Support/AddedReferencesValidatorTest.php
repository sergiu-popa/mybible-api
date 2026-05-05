<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Support;

use App\Domain\AI\Support\AddedReferencesValidator;
use App\Domain\Security\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddedReferencesValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_keeps_valid_reference_anchors(): void
    {
        $validator = new AddedReferencesValidator;

        $html = '<p>See <a class="reference" href="JHN.3:16.VDC">John 3:16</a>.</p>';

        $result = $validator->validate($html);

        self::assertSame(1, $result['references_added']);
        self::assertStringContainsString('class="reference"', $result['html']);
        self::assertStringContainsString('href="JHN.3:16.VDC"', $result['html']);
    }

    public function test_strips_anchors_missing_reference_class(): void
    {
        $validator = new AddedReferencesValidator;

        $html = '<p>See <a href="JHN.3:16.VDC">John 3:16</a>.</p>';

        $result = $validator->validate($html);

        self::assertSame(0, $result['references_added']);
        self::assertStringNotContainsString('<a ', $result['html']);
        self::assertStringContainsString('John 3:16', $result['html']);
        self::assertSame(1, SecurityEvent::query()->where('event', AddedReferencesValidator::SECURITY_EVENT)->count());
    }

    public function test_strips_anchors_with_invalid_href(): void
    {
        $validator = new AddedReferencesValidator;

        $html = '<p>Bad <a class="reference" href="not-a-reference">link</a>.</p>';

        $result = $validator->validate($html);

        self::assertSame(0, $result['references_added']);
        self::assertStringNotContainsString('<a ', $result['html']);
        self::assertStringContainsString('link', $result['html']);
        self::assertSame(1, SecurityEvent::query()->count());
    }

    public function test_counts_only_surviving_anchors(): void
    {
        $validator = new AddedReferencesValidator;

        $html = '<p><a class="reference" href="JHN.3:16.VDC">A</a>'
            . '<a href="JHN.3:17.VDC">B</a>'
            . '<a class="reference" href="ROM.1:1.VDC">C</a></p>';

        $result = $validator->validate($html);

        self::assertSame(2, $result['references_added']);
        self::assertStringContainsString('B', $result['html']);
    }

    public function test_handles_empty_html(): void
    {
        $validator = new AddedReferencesValidator;

        $result = $validator->validate('');

        self::assertSame(0, $result['references_added']);
        self::assertSame('', $result['html']);
    }
}
