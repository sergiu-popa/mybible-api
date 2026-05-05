<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\Models\AiCall;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\Commentary\Actions\CorrectCommentaryTextAction;
use App\Domain\Commentary\DataTransferObjects\AICorrectCommentaryTextData;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CorrectCommentaryTextActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.api_url', 'https://api.anthropic.test');
        config()->set('ai.retry.max_attempts', 1);
        config()->set('ai.retry.backoff_ms', [0]);
    }

    public function test_writes_plain_and_stamps_with_corrected_html(): void
    {
        $commentary = Commentary::factory()->create(['language' => 'ro']);
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'original' => '<p>Original text.</p>',
            'content' => '<p>Original text.</p>',
        ]);

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p>Corrected text.</p>']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
            ], 200),
        ]);

        $action = $this->makeAction();

        $updated = $action->execute(new AICorrectCommentaryTextData($text));

        self::assertSame('<p>Corrected text.</p>', $updated->plain);
        self::assertNotNull($updated->ai_corrected_at);
        self::assertSame('1.0.0', $updated->ai_corrected_prompt_version);
        self::assertSame(1, AiCall::query()->where('prompt_name', 'commentary_correct')->count());
    }

    public function test_seeds_original_when_missing(): void
    {
        $commentary = Commentary::factory()->create(['language' => 'ro']);
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'original' => null,
            'content' => '<p>Legacy content.</p>',
        ]);

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p>Corrected.</p>']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $action = $this->makeAction();
        $updated = $action->execute(new AICorrectCommentaryTextData($text));

        self::assertSame('<p>Legacy content.</p>', $updated->original);
        self::assertSame('<p>Corrected.</p>', $updated->plain);
    }

    private function makeAction(): CorrectCommentaryTextAction
    {
        return new CorrectCommentaryTextAction(
            new ClaudeClient($this->app->make(HttpFactory::class)),
            $this->app->make(PromptRegistry::class),
        );
    }
}
