<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Domain\Notes\Models\Note;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        // Each test gets a fresh limiter state.
        $this->app->make(RateLimiter::class)->clear('public-anon');
        $this->app->make(RateLimiter::class)->clear('per-user');
    }

    public function test_public_anon_limiter_is_configured_at_180_per_minute(): void
    {
        $limiter = $this->app->make(RateLimiter::class);
        $callback = $limiter->limiter('public-anon');
        $this->assertNotNull($callback, 'public-anon limiter must be registered.');

        $request = Request::create('/api/v1/news', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.5');

        $limit = $callback($request);
        $this->assertSame(180, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }

    public function test_per_user_limiter_is_configured_at_300_per_minute(): void
    {
        $limiter = $this->app->make(RateLimiter::class);
        $callback = $limiter->limiter('per-user');
        $this->assertNotNull($callback, 'per-user limiter must be registered.');

        $request = Request::create('/api/v1/notes', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.6');

        $limit = $callback($request);
        $this->assertSame(300, $limit->maxAttempts);
    }

    public function test_public_route_returns_429_after_180_hits_from_one_ip(): void
    {
        for ($i = 1; $i <= 180; $i++) {
            $response = $this->withHeaders($this->apiKeyHeaders())
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->getJson(route('news.index'));
            $this->assertNotSame(
                429,
                $response->getStatusCode(),
                "Request {$i} should not be throttled (limit is 180).",
            );
        }

        $throttled = $this->withHeaders($this->apiKeyHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->getJson(route('news.index'));

        $throttled->assertStatus(429);
        $this->assertNotNull(
            $throttled->headers->get('Retry-After'),
            'A throttled response must include the Retry-After header.',
        );
    }

    public function test_authenticated_route_returns_429_after_300_hits_from_one_user(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        Note::factory()->for($user)->create();

        for ($i = 1; $i <= 300; $i++) {
            $response = $this->getJson(route('notes.index'));
            $this->assertNotSame(
                429,
                $response->getStatusCode(),
                "Request {$i} should not be throttled (limit is 300).",
            );
        }

        $this->getJson(route('notes.index'))->assertStatus(429);
    }

    public function test_up_route_is_not_rate_limited(): void
    {
        for ($i = 1; $i <= 250; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->getJson('/up');
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "/up should never throttle; failed at iteration {$i}.",
            );
        }
    }

    public function test_throttled_response_emits_x_rate_limit_headers(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.30'])
            ->getJson(route('news.index'));

        $response->assertOk();
        $this->assertNotNull(
            $response->headers->get('X-RateLimit-Limit'),
            'Throttle middleware must emit X-RateLimit-Limit on every response.',
        );
        $this->assertNotNull(
            $response->headers->get('X-RateLimit-Remaining'),
            'Throttle middleware must emit X-RateLimit-Remaining on every response.',
        );
    }
}
