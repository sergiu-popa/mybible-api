<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QrCode;

use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Reference;
use App\Http\Requests\QrCode\ShowQrCodeRequest;
use App\Http\Rules\ValidReference;
use Tests\TestCase;

final class ShowQrCodeRequestTest extends TestCase
{
    public function test_canonical_reference_reads_the_parsed_reference_from_the_request(): void
    {
        $request = ShowQrCodeRequest::create('/api/v1/qr-codes?reference=GEN.1:1.VDC', 'GET');

        $request->attributes->set(
            ValidReference::PARSED_ATTRIBUTE_KEY,
            new Reference('GEN', 1, [1], 'VDC'),
        );

        $this->assertSame(
            'GEN.1:1.VDC',
            $request->canonicalReference(new ReferenceFormatter),
        );
    }

    public function test_canonical_reference_falls_back_to_raw_input_when_version_is_missing(): void
    {
        $request = ShowQrCodeRequest::create('/api/v1/qr-codes?reference=GEN.1:1', 'GET');

        $request->attributes->set(
            ValidReference::PARSED_ATTRIBUTE_KEY,
            new Reference('GEN', 1, [1], null),
        );

        $this->assertSame('GEN.1:1', $request->canonicalReference(new ReferenceFormatter));
    }

    public function test_canonical_reference_throws_when_nothing_was_parsed(): void
    {
        $request = ShowQrCodeRequest::create('/api/v1/qr-codes', 'GET');

        $this->expectException(\RuntimeException::class);

        $request->canonicalReference(new ReferenceFormatter);
    }
}
