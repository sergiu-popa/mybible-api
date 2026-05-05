<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Commentary;

use App\Application\Jobs\AddReferencesCommentaryBatchJob;
use App\Application\Jobs\CorrectCommentaryBatchJob;
use App\Application\Jobs\ExportCommentarySqliteJob;
use App\Application\Jobs\TranslateCommentaryJob;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CommentaryAiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.api_url', 'https://api.anthropic.test');
        config()->set('ai.retry.max_attempts', 1);
        config()->set('ai.retry.backoff_ms', [0]);

        BibleVersion::factory()->romanian()->create();
    }

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_ai_correct_per_row_unauth_is_rejected(): void
    {
        $text = CommentaryText::factory()->create();
        $this->postJson(route('admin.commentary-texts.ai-correct', ['text' => $text->id]))
            ->assertUnauthorized();
    }

    public function test_ai_correct_per_row_non_super_is_forbidden(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $text = CommentaryText::factory()->create();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.commentary-texts.ai-correct', ['text' => $text->id]))
            ->assertForbidden();
    }

    public function test_ai_correct_per_row_writes_plain_and_returns_admin_resource(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create(['language' => 'ro']);
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'original' => '<p>raw</p>',
            'content' => '<p>raw</p>',
        ]);

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p>fixed</p>']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->postJson(route('admin.commentary-texts.ai-correct', ['text' => $text->id]))
            ->assertOk()
            ->assertJsonPath('data.plain', '<p>fixed</p>')
            ->assertJsonPath('data.ai_corrected_prompt_version', 'commentary_correct@1.0.0');
    }

    public function test_ai_add_references_per_row_writes_with_references(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create(['language' => 'ro']);
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'plain' => '<p>See John 3:16.</p>',
        ]);

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p>See <a class="reference" href="JHN.3:16.VDC">John 3:16</a>.</p>']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->postJson(route('admin.commentary-texts.ai-add-references', ['text' => $text->id]))
            ->assertOk()
            ->assertJsonPath('data.ai_referenced_prompt_version', 'add_references@1.0.0');
    }

    public function test_ai_correct_batch_dispatches_job_and_returns_202(): void
    {
        $this->actingAsSuper();
        Bus::fake();

        $commentary = Commentary::factory()->create();
        CommentaryText::factory()->count(2)->create(['commentary_id' => $commentary->id]);

        $this->postJson(route('admin.commentaries.ai-correct-batch', ['commentary' => $commentary->id]), [])
            ->assertAccepted()
            ->assertJsonPath('data.type', 'commentary.ai_correct')
            ->assertJsonStructure(['data' => ['id', 'status', 'progress']]);

        Bus::assertDispatched(CorrectCommentaryBatchJob::class);
    }

    public function test_ai_add_references_batch_dispatches_job(): void
    {
        $this->actingAsSuper();
        Bus::fake();

        $commentary = Commentary::factory()->create();

        $this->postJson(route('admin.commentaries.ai-add-references-batch', ['commentary' => $commentary->id]), [
            'book' => 'GEN',
            'chapter' => 1,
        ])
            ->assertAccepted();

        Bus::assertDispatched(AddReferencesCommentaryBatchJob::class);
    }

    public function test_translate_returns_409_when_target_exists_and_no_overwrite(): void
    {
        $this->actingAsSuper();

        $source = Commentary::factory()->create(['language' => 'ro']);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => '<p>x</p>',
        ]);

        Commentary::factory()->create([
            'language' => 'en',
            'source_commentary_id' => $source->id,
        ]);

        $this->postJson(route('admin.commentaries.translate', ['commentary' => $source->id]), [
            'target_language' => 'en',
        ])
            ->assertStatus(409);
    }

    public function test_translate_dispatches_job_and_creates_target(): void
    {
        $this->actingAsSuper();
        Bus::fake();

        $source = Commentary::factory()->create(['language' => 'ro', 'slug' => 'sda-ro']);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => '<p>x</p>',
        ]);

        $this->postJson(route('admin.commentaries.translate', ['commentary' => $source->id]), [
            'target_language' => 'en',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.type', 'commentary.translate');

        self::assertSame(1, Commentary::query()
            ->where('source_commentary_id', $source->id)
            ->where('language', 'en')
            ->count());

        Bus::assertDispatched(TranslateCommentaryJob::class);
    }

    public function test_translate_validates_target_language(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        $this->postJson(route('admin.commentaries.translate', ['commentary' => $commentary->id]), [
            'target_language' => 'xx',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_language']);
    }

    public function test_sqlite_export_dispatches_job_and_returns_202(): void
    {
        $this->actingAsSuper();
        Bus::fake();

        $commentary = Commentary::factory()->create();

        $this->postJson(route('admin.commentaries.sqlite-export', ['commentary' => $commentary->id]))
            ->assertAccepted()
            ->assertJsonPath('data.type', 'commentary.sqlite_export');

        Bus::assertDispatched(ExportCommentarySqliteJob::class);
    }
}
