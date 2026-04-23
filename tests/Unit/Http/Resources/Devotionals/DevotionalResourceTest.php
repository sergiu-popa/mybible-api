<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Devotionals;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\Models\Devotional;
use App\Http\Resources\Devotionals\DevotionalResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class DevotionalResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_full_shape_including_optional_fields(): void
    {
        $devotional = Devotional::factory()->create([
            'date' => '2026-04-22',
            'language' => 'ro',
            'type' => DevotionalType::Adults,
            'title' => 'A title',
            'content' => 'Some content',
            'passage' => 'JHN.3:16',
            'author' => 'Jane Doe',
        ]);

        $array = DevotionalResource::make($devotional)->toArray(new Request);

        $this->assertSame($devotional->id, $array['id']);
        $this->assertSame('2026-04-22', $array['date']);
        $this->assertSame('adults', $array['type']);
        $this->assertSame('ro', $array['language']);
        $this->assertSame('A title', $array['title']);
        $this->assertSame('Some content', $array['content']);
        $this->assertSame('JHN.3:16', $array['passage']);
        $this->assertSame('Jane Doe', $array['author']);
    }

    public function test_it_omits_optional_fields_when_null(): void
    {
        $devotional = Devotional::factory()->create([
            'date' => '2026-04-22',
            'passage' => null,
            'author' => null,
        ]);

        $resolved = DevotionalResource::make($devotional)->resolve();

        $this->assertArrayNotHasKey('passage', $resolved);
        $this->assertArrayNotHasKey('author', $resolved);
    }
}
