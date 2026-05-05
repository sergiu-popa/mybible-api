<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Commentary;

use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class SubmitCommentaryErrorReportTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyClient();
        RateLimiter::clear('commentary-error-reports');
    }

    public function test_anonymous_submission_creates_report_and_increments_counter(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 0]);

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('commentary-texts.error-reports.store', ['text' => $text->id]), [
                'description' => 'Typo in line 1.',
                'verse' => 3,
                'device_id' => 'd1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.commentary_text_id', $text->id)
            ->assertJsonPath('data.status', 'pending');

        self::assertSame(1, CommentaryErrorReport::query()->count());
        self::assertSame(1, (int) $text->refresh()->errors_reported);
    }

    public function test_missing_description_returns_422(): void
    {
        $text = CommentaryText::factory()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('commentary-texts.error-reports.store', ['text' => $text->id]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_throttle_limits_after_five_per_minute(): void
    {
        $text = CommentaryText::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->apiKeyHeaders())
                ->postJson(route('commentary-texts.error-reports.store', ['text' => $text->id]), [
                    'description' => "report {$i}",
                ])
                ->assertCreated();
        }

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('commentary-texts.error-reports.store', ['text' => $text->id]), [
                'description' => 'sixth',
            ])
            ->assertStatus(429);
    }
}
