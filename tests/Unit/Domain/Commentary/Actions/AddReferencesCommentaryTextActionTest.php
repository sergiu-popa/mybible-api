<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\AI\Actions\AddReferencesAction;
use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\AI\Support\AddedReferencesValidator;
use App\Domain\AI\Support\AddReferencesVersionResolver;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Commentary\Actions\AddReferencesCommentaryTextAction;
use App\Domain\Commentary\DataTransferObjects\AIAddReferencesCommentaryTextData;
use App\Domain\Commentary\Exceptions\CommentaryTextNotCorrectedException;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AddReferencesCommentaryTextActionTest extends TestCase
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

    public function test_writes_with_references_and_stamps_against_plain_html(): void
    {
        BibleVersion::factory()->romanian()->create();

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

        $updated = $this->makeAction()->execute(new AIAddReferencesCommentaryTextData($text));

        self::assertStringContainsString('class="reference"', (string) $updated->with_references);
        self::assertNotNull($updated->ai_referenced_at);
        self::assertSame('1.0.0', $updated->ai_referenced_prompt_version);
    }

    public function test_throws_when_plain_is_missing(): void
    {
        $commentary = Commentary::factory()->create(['language' => 'ro']);
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'plain' => null,
        ]);

        $this->expectException(CommentaryTextNotCorrectedException::class);

        $this->makeAction()->execute(new AIAddReferencesCommentaryTextData($text));
    }

    private function makeAction(): AddReferencesCommentaryTextAction
    {
        $client = new ClaudeClient($this->app->make(HttpFactory::class));
        $addReferences = new AddReferencesAction(
            $client,
            $this->app->make(PromptRegistry::class),
            new AddReferencesVersionResolver,
            new AddedReferencesValidator,
        );

        return new AddReferencesCommentaryTextAction($addReferences);
    }
}
