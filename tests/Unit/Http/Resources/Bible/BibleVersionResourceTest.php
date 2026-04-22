<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleVersion;
use App\Http\Resources\Bible\BibleVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BibleVersionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_version_payload(): void
    {
        $version = BibleVersion::factory()->create([
            'name' => 'King James Version',
            'abbreviation' => 'KJV',
            'language' => 'en',
        ]);

        $payload = (new BibleVersionResource($version))->toArray(Request::create('/'));

        $this->assertSame([
            'id' => $version->id,
            'name' => 'King James Version',
            'abbreviation' => 'KJV',
            'language' => 'en',
        ], $payload);
    }
}
