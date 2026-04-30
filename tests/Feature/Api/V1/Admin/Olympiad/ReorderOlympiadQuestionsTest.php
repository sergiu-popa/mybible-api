<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\OlympiadCacheKeys;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ReorderOlympiadQuestionsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function genTheme(): array
    {
        return ['book' => 'GEN', 'chapters' => '1-3', 'language' => 'en'];
    }

    public function test_it_persists_full_ordering_inside_a_theme(): void
    {
        $this->actingAsAdmin();

        $a = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $b = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $c = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();

        $this->postJson(route('admin.olympiad.themes.questions.reorder', $this->genTheme()), [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);
    }

    public function test_it_returns_422_when_ids_belong_to_a_different_theme(): void
    {
        $this->actingAsAdmin();

        $insider = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $foreigner = OlympiadQuestion::factory()->forTheme('EXO', 1, 3, Language::En)->create([
            'position' => 99,
        ]);

        $this->postJson(route('admin.olympiad.themes.questions.reorder', $this->genTheme()), [
            'ids' => [$insider->id, $foreigner->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        // Foreigner unchanged; insider not touched because the action aborted.
        $this->assertSame(99, $foreigner->refresh()->position);
    }

    public function test_it_returns_422_when_an_id_does_not_exist(): void
    {
        $this->actingAsAdmin();

        $real = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();

        $this->postJson(route('admin.olympiad.themes.questions.reorder', $this->genTheme()), [
            'ids' => [$real->id, 999_999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_it_validates_book_in_path(): void
    {
        $this->actingAsAdmin();

        $this->postJson(
            route('admin.olympiad.themes.questions.reorder', [
                'book' => 'XXX',
                'chapters' => '1-3',
                'language' => 'en',
            ]),
            ['ids' => [1]],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book']);
    }

    public function test_it_validates_language_in_path(): void
    {
        $this->actingAsAdmin();

        $this->postJson(
            route('admin.olympiad.themes.questions.reorder', [
                'book' => 'GEN',
                'chapters' => '1-3',
                'language' => 'xx',
            ]),
            ['ids' => [1]],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    public function test_it_returns_422_for_malformed_chapter_segment(): void
    {
        $this->actingAsAdmin();

        $this->postJson(
            route('admin.olympiad.themes.questions.reorder', [
                'book' => 'GEN',
                'chapters' => 'bad',
                'language' => 'en',
            ]),
            ['ids' => [1]],
        )->assertUnprocessable();
    }

    public function test_reorder_invalidates_the_public_read_cache(): void
    {
        $this->setUpApiKeyClient();

        $a = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $b = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $c = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        foreach ([$a, $b, $c] as $question) {
            OlympiadAnswer::factory()->create(['olympiad_question_id' => $question->id]);
        }

        // Prime the cache via the public read path.
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', $this->genTheme()))
            ->assertOk();

        $cacheKey = OlympiadCacheKeys::themeQuestions(
            'GEN',
            new ChapterRange(1, 3),
            Language::En,
        );
        $cacheTags = OlympiadCacheKeys::tagsForTheme(
            'GEN',
            new ChapterRange(1, 3),
            Language::En,
        );
        $this->assertNotNull(Cache::tags($cacheTags)->get($cacheKey));

        $this->actingAsAdmin();
        $this->postJson(route('admin.olympiad.themes.questions.reorder', $this->genTheme()), [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertNull(Cache::tags($cacheTags)->get($cacheKey));
    }

    public function test_it_blocks_non_admin_users(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.olympiad.themes.questions.reorder', $this->genTheme()), ['ids' => [1]])
            ->assertForbidden();
    }
}
