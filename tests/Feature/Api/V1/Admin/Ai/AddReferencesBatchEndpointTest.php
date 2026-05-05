<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Ai;

use App\Application\Jobs\AddReferencesBatchJob;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class AddReferencesBatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_dispatches_job_and_returns_202_with_import_job(): void
    {
        $this->actingAsSuper();

        Bus::fake();

        $this->postJson(route('admin.ai.add-references.batch'), [
            'subject_type' => 'commentary_text',
            'subject_id' => 1,
            'language' => 'ro',
            'filters' => ['book' => 'ROM'],
        ])
            ->assertAccepted()
            ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress']])
            ->assertJsonPath('data.type', 'ai.add_references');

        Bus::assertDispatched(AddReferencesBatchJob::class, function (AddReferencesBatchJob $job): bool {
            return $job->subjectType === 'commentary_text'
                && $job->subjectId === 1
                && $job->language === 'ro'
                && $job->filters === ['book' => 'ROM'];
        });

        self::assertSame(1, ImportJob::query()->where('type', 'ai.add_references')->count());
    }

    public function test_validation_rejects_unknown_subject_type(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.ai.add-references.batch'), [
            'subject_type' => 'unknown',
            'subject_id' => 1,
            'language' => 'ro',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_type']);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->postJson(route('admin.ai.add-references.batch'), [])
            ->assertUnauthorized();
    }
}
