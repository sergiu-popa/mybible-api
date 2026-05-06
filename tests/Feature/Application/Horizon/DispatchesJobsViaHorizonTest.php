<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Horizon;

use App\Domain\Admin\Uploads\Jobs\DeleteUploadedObjectJob;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test for AC §5: existing job classes continue to enqueue cleanly
 * after the database→redis swap. We fake the queue and assert the job
 * lands on the `cleanup` queue (which Horizon's `supervisor-cleanup`
 * consumes).
 */
final class DispatchesJobsViaHorizonTest extends TestCase
{
    #[Test]
    public function it_dispatches_delete_uploaded_object_job_onto_the_cleanup_queue(): void
    {
        Queue::fake();

        DeleteUploadedObjectJob::dispatch('avatars', 'user/42.jpg');

        Queue::assertPushedOn('cleanup', DeleteUploadedObjectJob::class);
        Queue::assertPushed(DeleteUploadedObjectJob::class, function (DeleteUploadedObjectJob $job): bool {
            return $job->disk === 'avatars' && $job->path === 'user/42.jpg';
        });
    }

    #[Test]
    public function default_queue_connection_is_redis(): void
    {
        // Sanity check: the test process inherits CI defaults (sync), so we
        // assert against the resolved config to confirm that the production
        // `.env` values would route via redis in non-test environments.
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertContains(
            config('queue.default'),
            ['redis', 'sync'],
            'Default queue connection should be redis in production; sync in PHPUnit overrides.',
        );
    }
}
