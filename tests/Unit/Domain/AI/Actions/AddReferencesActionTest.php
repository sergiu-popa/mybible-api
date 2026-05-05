<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Actions;

use App\Domain\AI\Actions\AddReferencesAction;
use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\DataTransferObjects\AddReferencesInput;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Exceptions\ClaudeUnavailableException;
use App\Domain\AI\Models\AiCall;
use App\Domain\AI\Prompts\PromptRegistry;
use App\Domain\AI\Support\AddedReferencesValidator;
use App\Domain\AI\Support\AddReferencesVersionResolver;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AddReferencesActionTest extends TestCase
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

    public function test_happy_path_returns_validated_html_and_count(): void
    {
        BibleVersion::factory()->romanian()->create();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p>See <a class="reference" href="JHN.3:16.VDC">John 3:16</a>.</p>']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $action = $this->makeAction();

        $output = $action->execute(new AddReferencesInput(
            html: '<p>See John 3:16.</p>',
            language: 'ro',
        ));

        self::assertSame(1, $output->referencesAdded);
        self::assertSame('1.0.0', $output->promptVersion);
        self::assertStringContainsString('class="reference"', $output->html);
        self::assertGreaterThan(0, $output->aiCallId);
    }

    public function test_explicit_input_version_overrides_settings(): void
    {
        BibleVersion::factory()->create(['abbreviation' => 'KJV']);
        $vdc = BibleVersion::factory()->romanian()->create();
        LanguageSetting::query()->where('language', 'ro')->update(['default_bible_version_id' => $vdc->id]);

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p></p>']],
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            ], 200),
        ]);

        $action = $this->makeAction();

        $action->execute(new AddReferencesInput(
            html: '<p>x</p>',
            language: 'ro',
            bibleVersionAbbreviation: 'KJV',
        ));

        Http::assertSent(function ($request): bool {
            $userMessage = $request->data()['messages'][0]['content'][0]['text'];

            return str_contains($userMessage, 'KJV');
        });

    }

    public function test_invalid_links_are_stripped_from_count(): void
    {
        BibleVersion::factory()->romanian()->create();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '<p><a class="reference" href="bogus">x</a> <a class="reference" href="JHN.3:16.VDC">y</a></p>']],
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            ], 200),
        ]);

        $action = $this->makeAction();

        $output = $action->execute(new AddReferencesInput(
            html: '<p>foo</p>',
            language: 'ro',
        ));

        self::assertSame(1, $output->referencesAdded);
    }

    public function test_throws_claude_unavailable_on_upstream_error(): void
    {
        BibleVersion::factory()->romanian()->create();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response(['error' => 'boom'], 500),
        ]);

        $action = $this->makeAction();

        $this->expectException(ClaudeUnavailableException::class);

        try {
            $action->execute(new AddReferencesInput(
                html: '<p>x</p>',
                language: 'ro',
            ));
        } finally {
            self::assertSame(1, AiCall::query()->where('status', AiCallStatus::Error->value)->count());
        }
    }

    private function makeAction(): AddReferencesAction
    {
        return new AddReferencesAction(
            new ClaudeClient($this->app->make(HttpFactory::class)),
            $this->app->make(PromptRegistry::class),
            new AddReferencesVersionResolver,
            new AddedReferencesValidator,
        );
    }
}
