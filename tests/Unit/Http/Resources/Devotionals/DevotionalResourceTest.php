<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Devotionals;

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
        $devotional = Devotional::factory()->adults()->create([
            'date' => '2026-04-22',
            'language' => 'ro',
            'title' => 'A title',
            'content' => 'Some content',
            'audio_cdn_url' => 'https://cdn.example.com/audio.mp3',
            'audio_embed' => '<iframe src="audio"></iframe>',
            'video_embed' => '<iframe src="video"></iframe>',
            'passage' => 'JHN.3:16',
            'author' => 'Jane Doe',
        ]);
        $devotional->load('typeRelation');

        $array = DevotionalResource::make($devotional)->toArray(new Request);

        $this->assertSame($devotional->id, $array['id']);
        $this->assertSame('2026-04-22', $array['date']);
        $this->assertSame('adults', $array['type']);
        $this->assertSame('ro', $array['language']);
        $this->assertSame('A title', $array['title']);
        $this->assertSame('Some content', $array['content']);
        $this->assertSame('https://cdn.example.com/audio.mp3', $array['audio_cdn_url']);
        $this->assertSame('<iframe src="audio"></iframe>', $array['audio_embed']);
        $this->assertSame('<iframe src="video"></iframe>', $array['video_embed']);
        $this->assertSame('JHN.3:16', $array['passage']);
        $this->assertSame('Jane Doe', $array['author']);
    }

    public function test_it_omits_optional_fields_when_null(): void
    {
        $devotional = Devotional::factory()->create([
            'date' => '2026-04-22',
            'audio_cdn_url' => null,
            'audio_embed' => null,
            'video_embed' => null,
            'passage' => null,
            'author' => null,
        ]);
        $devotional->load('typeRelation');

        $resolved = DevotionalResource::make($devotional)->resolve();

        $this->assertArrayNotHasKey('audio_cdn_url', $resolved);
        $this->assertArrayNotHasKey('audio_embed', $resolved);
        $this->assertArrayNotHasKey('video_embed', $resolved);
        $this->assertArrayNotHasKey('passage', $resolved);
        $this->assertArrayNotHasKey('author', $resolved);
    }
}
