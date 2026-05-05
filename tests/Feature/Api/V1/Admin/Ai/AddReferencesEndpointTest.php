<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Ai;

use App\Domain\AI\Models\AiCall;
use App\Domain\Bible\Models\BibleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AddReferencesEndpointTest extends TestCase
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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('admin.ai.add-references'), [
            'html' => '<p>x</p>',
            'language' => 'ro',
        ])->assertUnauthorized();
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.ai.add-references'), [
                'html' => '<p>x</p>',
                'language' => 'ro',
            ])
            ->assertForbidden();
    }

    public function test_missing_fields_return_422(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.ai.add-references'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['html', 'language']);
    }

    public function test_happy_path_returns_validated_html_and_writes_audit_row(): void
    {
        $this->actingAsSuper();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '<p>See <a class="reference" href="JHN.3:16.VDC">John 3:16</a>.</p>',
                ]],
                'usage' => [
                    'input_tokens' => 11,
                    'output_tokens' => 13,
                    'cache_creation_input_tokens' => 0,
                    'cache_read_input_tokens' => 0,
                ],
            ], 200),
        ]);

        $this->postJson(route('admin.ai.add-references'), [
            'html' => '<p>See John 3:16.</p>',
            'language' => 'ro',
        ])
            ->assertOk()
            ->assertJsonPath('data.references_added', 1)
            ->assertJsonPath('data.prompt_version', '1.0.0')
            ->assertJsonStructure(['data' => ['html', 'references_added', 'prompt_version', 'ai_call_id']]);

        self::assertSame(1, AiCall::query()->count());
    }

    public function test_returns_502_with_retry_after_on_claude_failure(): void
    {
        $this->actingAsSuper();

        Http::fake([
            'api.anthropic.test/v1/messages' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->postJson(route('admin.ai.add-references'), [
            'html' => '<p>x</p>',
            'language' => 'ro',
        ]);

        $response->assertStatus(502);
        self::assertNotEmpty($response->headers->get('Retry-After'));
    }
}
