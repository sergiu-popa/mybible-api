<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\EducationalResources\Support;

use App\Domain\EducationalResources\Support\MediaUrlResolver;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaUrlResolverTest extends TestCase
{
    public function test_it_returns_null_for_a_null_path(): void
    {
        $this->assertNull(MediaUrlResolver::absoluteUrl(null, 'public'));
    }

    public function test_it_returns_null_for_an_empty_string_path(): void
    {
        $this->assertNull(MediaUrlResolver::absoluteUrl('', 'public'));
    }

    public function test_it_returns_the_absolute_url_for_a_valid_path(): void
    {
        Storage::fake('public');

        $url = MediaUrlResolver::absoluteUrl('resources/thing.pdf', 'public');

        $this->assertIsString($url);
        $this->assertStringContainsString('resources/thing.pdf', (string) $url);
    }

    public function test_it_honours_the_requested_disk(): void
    {
        Storage::fake('s3');

        $url = MediaUrlResolver::absoluteUrl('docs/paper.pdf', 's3');

        $this->assertIsString($url);
        $this->assertStringContainsString('docs/paper.pdf', (string) $url);
    }
}
