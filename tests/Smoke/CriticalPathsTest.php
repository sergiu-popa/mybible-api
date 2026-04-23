<?php

declare(strict_types=1);

namespace Tests\Smoke;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Post-cutover "is it up?" smoke suite.
 *
 * Hits the 5 critical endpoints over the wire against a live target
 * URL (dev, staging, or production). Deliberately asserts status
 * codes only — correctness is the job of the regular feature suite.
 *
 * Runs via `make smoke`. Excluded from the default suite via
 * phpunit.xml's <defaultTestSuite> selection and the @group filter
 * so a forgotten env var never red-lights CI.
 *
 * Required env:
 *   SMOKE_TARGET_URL     base URL, e.g. https://api.mybible.eu
 *   SMOKE_API_KEY        an api-key recognised by EnsureValidApiKey
 *   SMOKE_USER_EMAIL     dedicated smoke-test user (not a real user)
 *   SMOKE_USER_PASSWORD  password for that user
 */
#[Group('smoke')]
final class CriticalPathsTest extends TestCase
{
    private string $baseUrl;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $baseUrl = getenv('SMOKE_TARGET_URL');
        $apiKey = getenv('SMOKE_API_KEY');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($apiKey) || $apiKey === '') {
            $this->markTestSkipped('SMOKE_TARGET_URL and SMOKE_API_KEY must be set to run smoke tests.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function test_health_check_returns_ok(): void
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/up");

        $this->assertSame(200, $response->status(), "GET /up failed: {$response->body()}");
    }

    public function test_bible_versions_endpoint_returns_ok(): void
    {
        $response = Http::withHeaders($this->apiKeyHeaders())
            ->timeout(10)
            ->get("{$this->baseUrl}/api/v1/bible-versions", ['language' => 'ro']);

        $this->assertSame(200, $response->status(), "GET /api/v1/bible-versions failed: {$response->body()}");
    }

    public function test_books_endpoint_returns_ok(): void
    {
        $response = Http::withHeaders($this->apiKeyHeaders())
            ->timeout(10)
            ->get("{$this->baseUrl}/api/v1/books", ['language' => 'ro']);

        $this->assertSame(200, $response->status(), "GET /api/v1/books failed: {$response->body()}");
    }

    public function test_login_and_me_round_trip(): void
    {
        $email = getenv('SMOKE_USER_EMAIL');
        $password = getenv('SMOKE_USER_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->markTestSkipped('SMOKE_USER_EMAIL and SMOKE_USER_PASSWORD must be set.');
        }

        $loginResponse = Http::withHeaders($this->apiKeyHeaders())
            ->timeout(10)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/v1/auth/login", [
                'email' => $email,
                'password' => $password,
            ]);

        $this->assertSame(200, $loginResponse->status(), "POST /api/v1/auth/login failed: {$loginResponse->body()}");

        $token = $loginResponse->json('data.token') ?? $loginResponse->json('token');
        $this->assertIsString($token, 'login response did not include a token.');

        $meResponse = Http::withToken($token)
            ->timeout(10)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/auth/me");

        $this->assertSame(200, $meResponse->status(), "GET /api/v1/auth/me failed: {$meResponse->body()}");
    }

    /**
     * @return array<string, string>
     */
    private function apiKeyHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }
}
