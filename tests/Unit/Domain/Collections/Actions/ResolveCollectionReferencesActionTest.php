<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Collections\Actions;

use App\Domain\Collections\Actions\ResolveCollectionReferencesAction;
use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ResolveCollectionReferencesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_valid_references(): void
    {
        $topic = CollectionTopic::factory()->english()->create();
        $reference = CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
        ]);

        $result = (new ResolveCollectionReferencesAction)->handle([$reference], Language::En);

        $this->assertCount(1, $result);
        $this->assertSame('GEN.1:1.VDC', $result[0]->raw);
        $this->assertNull($result[0]->parseError);
        $this->assertIsArray($result[0]->parsed);
        $this->assertSame('GEN', $result[0]->parsed[0]['book']);
        $this->assertSame(1, $result[0]->parsed[0]['chapter']);
        $this->assertSame([1], $result[0]->parsed[0]['verses']);
        $this->assertSame('VDC', $result[0]->parsed[0]['version']);
        $this->assertIsString($result[0]->displayText);
        $this->assertNotSame('', $result[0]->displayText);
    }

    public function test_it_returns_parse_error_for_malformed_references_and_logs_warning(): void
    {
        $spy = Log::spy();

        $topic = CollectionTopic::factory()->english()->create();
        $good = CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
        ]);
        $bad = CollectionReference::factory()->malformed()->create([
            'collection_topic_id' => $topic->id,
        ]);

        $result = (new ResolveCollectionReferencesAction)->handle([$good, $bad], Language::En);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->parseError);

        $this->assertNull($result[1]->parsed);
        $this->assertNull($result[1]->displayText);
        $this->assertIsString($result[1]->parseError);
        $this->assertNotSame('', $result[1]->parseError);

        /** @phpstan-ignore-next-line method.notFound */
        $spy->shouldHaveReceived('warning')->once();
    }

    public function test_it_formats_display_text_for_romanian_language(): void
    {
        $topic = CollectionTopic::factory()->romanian()->create();
        $reference = CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
        ]);

        $result = (new ResolveCollectionReferencesAction)->handle([$reference], Language::Ro);

        $this->assertIsString($result[0]->displayText);
        // Romanian formatter emits a localised book name (not "Genesis").
        $this->assertNotSame('', $result[0]->displayText);
    }

    public function test_it_returns_empty_array_for_empty_input(): void
    {
        $result = (new ResolveCollectionReferencesAction)->handle([], Language::En);

        $this->assertSame([], $result);
    }

    public function test_it_returns_parse_error_for_cross_chapter_ranges(): void
    {
        $spy = Log::spy();

        $topic = CollectionTopic::factory()->english()->create();
        $reference = CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'MAT.19:27-20:16.VDC',
        ]);

        $result = (new ResolveCollectionReferencesAction)->handle([$reference], Language::En);

        $this->assertCount(1, $result);
        $this->assertSame('MAT.19:27-20:16.VDC', $result[0]->raw);
        $this->assertNull($result[0]->parsed);
        $this->assertNull($result[0]->displayText);
        $this->assertSame(
            'Cross-chapter ranges are not supported in collections.',
            $result[0]->parseError,
        );

        /** @phpstan-ignore-next-line method.notFound */
        $spy->shouldHaveReceived('warning')->once();
    }

    public function test_it_resolves_multi_reference_strings(): void
    {
        $topic = CollectionTopic::factory()->english()->create();
        $reference = CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            // Canonical multi-reference: `BOOK.passage;passage;....VERSION`.
            'reference' => 'GEN.1:1;2;3.VDC',
        ]);

        $result = (new ResolveCollectionReferencesAction)->handle([$reference], Language::En);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]->parseError);
        $this->assertIsArray($result[0]->parsed);
        $this->assertCount(3, $result[0]->parsed);
    }
}
