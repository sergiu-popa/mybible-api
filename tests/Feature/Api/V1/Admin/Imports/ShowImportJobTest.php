<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Imports;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowImportJobTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_it_returns_a_running_job_payload(): void
    {
        $this->actingAsAdmin();

        $job = ImportJob::factory()->running(42)->create([
            'type' => 'bible.catalog',
            'payload' => ['version' => 'VDC'],
        ]);

        $this->getJson(route('admin.imports.show', ['job' => $job->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.type', 'bible.catalog')
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.progress', 42)
            ->assertJsonPath('data.payload', ['version' => 'VDC']);
    }

    public function test_it_returns_a_failed_job_payload_with_error(): void
    {
        $this->actingAsAdmin();

        $job = ImportJob::factory()->failed('database timeout')->create();

        $this->getJson(route('admin.imports.show', ['job' => $job->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'database timeout')
            ->assertJsonPath('data.finished_at', $job->finished_at?->toIso8601String());
    }

    public function test_it_returns_404_for_unknown_job(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.imports.show', ['job' => 999_999]))
            ->assertNotFound();
    }

    public function test_it_blocks_unauthenticated_requests(): void
    {
        $job = ImportJob::factory()->create();

        $this->getJson(route('admin.imports.show', ['job' => $job->id]))
            ->assertUnauthorized();
    }

    public function test_it_blocks_non_admin_users(): void
    {
        $job = ImportJob::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.imports.show', ['job' => $job->id]))
            ->assertForbidden();
    }
}
