<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\EducationalResources;

use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminResourceDownloadsSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_super_admin_can_view_summary_for_short_range(): void
    {
        $this->actingAsSuper();

        $resource = EducationalResource::factory()->create();
        ResourceDownload::factory()->forResource($resource)->count(3)->create([
            'created_at' => now(),
        ]);

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => $from,
            'to' => $to,
            'group_by' => 'day',
        ]))->assertOk()
            ->assertJsonStructure([
                'data' => [['date', 'downloadable_type', 'downloadable_id', 'language', 'count', 'unique_devices']],
                'meta' => ['from', 'to', 'group_by'],
            ]);
    }

    public function test_long_range_returns_400_until_mba_030(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'day',
        ]))->assertStatus(400);
    }

    public function test_inclusive_seven_day_window_is_accepted(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => now()->subDays(6)->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'day',
        ]))->assertOk();
    }

    public function test_week_grouping_returns_400_until_mba_030(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'week',
        ]))->assertStatus(400);
    }

    public function test_month_grouping_returns_400_until_mba_030(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'month',
        ]))->assertStatus(400);
    }

    public function test_requires_super_admin(): void
    {
        $this->getJson(route('admin.resource-downloads.summary', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'day',
        ]))->assertUnauthorized();
    }
}
