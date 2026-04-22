<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Resources\Bible\BibleBookResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BibleBookResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_localized_name(): void
    {
        $book = BibleBook::factory()->genesis()->create();

        $request = Request::create('/');
        $request->attributes->set(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::Ro);

        $payload = (new BibleBookResource($book))->toArray($request);

        $this->assertSame('GEN', $payload['abbreviation']);
        $this->assertSame('Geneza', $payload['name']);
        $this->assertSame('old', $payload['testament']);
        $this->assertSame(1, $payload['position']);
        $this->assertSame(50, $payload['chapter_count']);
    }

    public function test_it_falls_back_to_english_when_language_is_missing(): void
    {
        $book = BibleBook::factory()->genesis()->create();

        $request = Request::create('/');

        $payload = (new BibleBookResource($book))->toArray($request);

        $this->assertSame('Genesis', $payload['name']);
    }
}
