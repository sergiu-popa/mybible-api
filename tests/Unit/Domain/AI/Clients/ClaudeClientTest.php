<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Clients;

use App\Domain\AI\Clients\ClaudeClient;
use App\Domain\AI\DataTransferObjects\ClaudeRequest;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Models\AiCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ClaudeClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.api_url', 'https://api.anthropic.test');
        config()->set('services.anthropic.version', '2023-06-01');
        config()->set('ai.retry.max_attempts', 3);
        config()->set('ai.retry.backoff_ms', [0, 0, 0]);
    }

    public function test_request_payload_marks_system_block_with_cache_control(): void
    {
        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'OK']],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'cache_creation_input_tokens' => 100,
                    'cache_read_input_tokens' => 200,
                ],
            ], 200),
        ]);

        $client = new ClaudeClient($this->app->make(HttpFactory::class));

        $client->send(new ClaudeRequest(
            promptName: 'add_references',
            promptVersion: '1.0.0',
            model: 'claude-sonnet-4-6',
            systemPrompt: 'system',
            userMessage: 'user',
        ));

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            self::assertSame('claude-sonnet-4-6', $body['model']);
            self::assertIsArray($body['system']);
            self::assertSame('text', $body['system'][0]['type']);
            self::assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);

            return $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }

    public function test_persists_ai_call_with_token_usage_on_success(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'hello']],
                'usage' => [
                    'input_tokens' => 12,
                    'output_tokens' => 7,
                    'cache_creation_input_tokens' => 33,
                    'cache_read_input_tokens' => 44,
                ],
            ], 200),
        ]);

        $client = new ClaudeClient($this->app->make(HttpFactory::class));

        $response = $client->send(new ClaudeRequest(
            promptName: 'add_references',
            promptVersion: '1.0.0',
            model: 'claude-sonnet-4-6',
            systemPrompt: 'system',
            userMessage: 'user',
            subjectType: 'commentary_text',
            subjectId: 99,
            triggeredByUserId: (int) $user->id,
        ));

        self::assertSame(AiCallStatus::Ok, $response->status);
        self::assertSame('hello', $response->content);

        $row = AiCall::query()->find($response->aiCallId);
        self::assertNotNull($row);
        self::assertSame(12, $row->input_tokens);
        self::assertSame(7, $row->output_tokens);
        self::assertSame(33, $row->cache_creation_input_tokens);
        self::assertSame(44, $row->cache_read_input_tokens);
        self::assertSame('add_references', $row->prompt_name);
        self::assertSame('1.0.0', $row->prompt_version);
        self::assertSame('commentary_text', $row->subject_type);
        self::assertSame(99, $row->subject_id);
        self::assertSame((int) $user->id, $row->triggered_by_user_id);
        self::assertSame(AiCallStatus::Ok, $row->status);
    }

    public function test_retries_on_429_then_succeeds(): void
    {
        Http::fake([
            'api.anthropic.test/v1/messages' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push([
                    'content' => [['type' => 'text', 'text' => 'final']],
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ], 200),
        ]);

        $client = new ClaudeClient($this->app->make(HttpFactory::class));

        $response = $client->send(new ClaudeRequest(
            promptName: 'add_references',
            promptVersion: '1.0.0',
            model: 'claude-sonnet-4-6',
            systemPrompt: 'system',
            userMessage: 'user',
        ));

        self::assertSame(AiCallStatus::Ok, $response->status);
        self::assertSame('final', $response->content);
        self::assertSame(1, AiCall::query()->where('status', AiCallStatus::Ok->value)->count());

        // 3 dispatched HTTP attempts, only one ai_calls row (on success).
        Http::assertSentCount(3);
    }

    public function test_writes_error_row_after_exhausting_retries(): void
    {
        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response(['error' => 'boom'], 500),
        ]);

        $client = new ClaudeClient($this->app->make(HttpFactory::class));

        $response = $client->send(new ClaudeRequest(
            promptName: 'add_references',
            promptVersion: '1.0.0',
            model: 'claude-sonnet-4-6',
            systemPrompt: 'system',
            userMessage: 'user',
        ));

        self::assertSame(AiCallStatus::Error, $response->status);
        self::assertNotSame('', (string) $response->errorMessage);

        $row = AiCall::query()->find($response->aiCallId);
        self::assertNotNull($row);
        self::assertSame(AiCallStatus::Error, $row->status);
        self::assertSame(0, $row->input_tokens);
    }
}
