<?php

declare(strict_types=1);

namespace App\Domain\AI\Clients;

use App\Domain\AI\DataTransferObjects\ClaudeRequest;
use App\Domain\AI\DataTransferObjects\ClaudeResponse;
use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Models\AiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as HttpResponse;
use Throwable;

/**
 * Thin HTTP wrapper around Anthropic's `/v1/messages` endpoint.
 *
 * Owns: cache-control on the system prompt block, retry+exponential
 * backoff on 429/5xx, and writing one `ai_calls` audit row per call
 * (success, error, or timeout). No business logic — callers shape the
 * prompt, this object just talks to the upstream.
 */
final class ClaudeClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function send(ClaudeRequest $request): ClaudeResponse
    {
        $maxAttempts = max(1, (int) config('ai.retry.max_attempts', 3));
        /** @var array<int, int> $backoff */
        $backoff = (array) config('ai.retry.backoff_ms', [500, 2000, 5000]);

        // Anchor latency on microtime (a monotonic-ish wall clock) rather
        // than Carbon, so test-time travel via Carbon::setTestNow() does not
        // distort the recorded latency_ms.
        $startedAt = microtime(true);
        $attempt = 0;
        $lastErrorMessage = null;
        $lastStatus = AiCallStatus::Error;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $this->dispatch($request);

                if ($response->successful()) {
                    return $this->onSuccess($request, $response, $startedAt);
                }

                $lastErrorMessage = sprintf(
                    'HTTP %d from Anthropic: %s',
                    $response->status(),
                    mb_substr((string) $response->body(), 0, 1024),
                );
                $lastStatus = AiCallStatus::Error;

                if (! $this->isRetryable($response->status()) || $attempt >= $maxAttempts) {
                    return $this->onFailure(
                        $request,
                        $startedAt,
                        $lastStatus,
                        $lastErrorMessage,
                    );
                }
            } catch (ConnectionException $e) {
                $lastErrorMessage = $e->getMessage();
                $lastStatus = AiCallStatus::Timeout;

                if ($attempt >= $maxAttempts) {
                    return $this->onFailure(
                        $request,
                        $startedAt,
                        AiCallStatus::Timeout,
                        $lastErrorMessage,
                    );
                }
            } catch (Throwable $e) {
                return $this->onFailure(
                    $request,
                    $startedAt,
                    AiCallStatus::Error,
                    $e->getMessage(),
                );
            }

            $sleepMs = $backoff[$attempt - 1] ?? end($backoff);
            if (is_int($sleepMs) && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $this->onFailure(
            $request,
            $startedAt,
            $lastStatus,
            $lastErrorMessage,
        );
    }

    private function dispatch(ClaudeRequest $request): HttpResponse
    {
        $apiKey = (string) config('services.anthropic.api_key', '');
        $apiUrl = rtrim((string) config('services.anthropic.api_url', 'https://api.anthropic.com'), '/');
        $version = (string) config('services.anthropic.version', '2023-06-01');
        $timeout = (int) config('ai.request.timeout_seconds', 60);

        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $request->systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $request->userMessage,
                        ],
                    ],
                ],
            ],
        ];

        return $this->http
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])
            ->timeout($timeout)
            ->post($apiUrl . '/v1/messages', $payload);
    }

    private function isRetryable(int $status): bool
    {
        if ($status === 429) {
            return true;
        }

        return $status >= 500 && $status < 600;
    }

    private function onSuccess(
        ClaudeRequest $request,
        HttpResponse $response,
        float $startedAt,
    ): ClaudeResponse {
        $latencyMs = $this->latencyMs($startedAt);
        $body = (array) $response->json();
        $usage = (array) ($body['usage'] ?? []);

        $content = $this->extractContent($body);

        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);
        $cacheCreation = (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);

        $audit = AiCall::query()->create([
            'prompt_version' => $request->promptVersion,
            'prompt_name' => $request->promptName,
            'model' => $request->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_creation_input_tokens' => $cacheCreation,
            'cache_read_input_tokens' => $cacheRead,
            'latency_ms' => $latencyMs,
            'status' => AiCallStatus::Ok,
            'error_message' => null,
            'subject_type' => $request->subjectType,
            'subject_id' => $request->subjectId,
            'triggered_by_user_id' => $request->triggeredByUserId,
        ]);

        return new ClaudeResponse(
            content: $content,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cacheCreationInputTokens: $cacheCreation,
            cacheReadInputTokens: $cacheRead,
            latencyMs: $latencyMs,
            status: AiCallStatus::Ok,
            errorMessage: null,
            aiCallId: (int) $audit->id,
        );
    }

    private function onFailure(
        ClaudeRequest $request,
        float $startedAt,
        AiCallStatus $status,
        ?string $errorMessage,
    ): ClaudeResponse {
        $latencyMs = $this->latencyMs($startedAt);

        $audit = AiCall::query()->create([
            'prompt_version' => $request->promptVersion,
            'prompt_name' => $request->promptName,
            'model' => $request->model,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'latency_ms' => $latencyMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'subject_type' => $request->subjectType,
            'subject_id' => $request->subjectId,
            'triggered_by_user_id' => $request->triggeredByUserId,
        ]);

        return new ClaudeResponse(
            content: '',
            inputTokens: 0,
            outputTokens: 0,
            cacheCreationInputTokens: 0,
            cacheReadInputTokens: 0,
            latencyMs: $latencyMs,
            status: $status,
            errorMessage: $errorMessage,
            aiCallId: (int) $audit->id,
        );
    }

    private function latencyMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractContent(array $body): string
    {
        $blocks = $body['content'] ?? [];
        if (! is_array($blocks)) {
            return '';
        }

        $parts = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? null) === 'text' && isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return implode('', $parts);
    }
}
