<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommentaryErrorReportsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_index_paginates_pending_reports(): void
    {
        $this->actingAsSuper();

        CommentaryErrorReport::factory()->count(3)->create();
        CommentaryErrorReport::factory()->fixed()->count(2)->create();

        $this->getJson(route('admin.commentary-error-reports.index', ['status' => 'pending']))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'commentary_text_id', 'status', 'description', 'reviewed_by_user_id']],
                'meta' => ['per_page'],
            ]);
    }

    public function test_unauth_index_is_rejected(): void
    {
        $this->getJson(route('admin.commentary-error-reports.index'))
            ->assertUnauthorized();
    }

    public function test_update_to_fixed_decrements_counter_and_records_reviewer(): void
    {
        $reviewer = $this->actingAsSuper();

        $text = CommentaryText::factory()->create(['errors_reported' => 1]);
        $report = CommentaryErrorReport::factory()->create([
            'commentary_text_id' => $text->id,
            'status' => CommentaryErrorReportStatus::Pending,
        ]);

        $this->patchJson(route('admin.commentary-error-reports.update', ['report' => $report->id]), [
            'status' => 'fixed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'fixed')
            ->assertJsonPath('data.reviewed_by_user_id', $reviewer->id);

        self::assertSame(0, (int) $text->refresh()->errors_reported);
    }

    public function test_update_invalid_status_returns_422(): void
    {
        $this->actingAsSuper();

        $report = CommentaryErrorReport::factory()->create();

        $this->patchJson(route('admin.commentary-error-reports.update', ['report' => $report->id]), [
            'status' => 'bogus',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
