<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\EducationalResources;

use App\Domain\Admin\Uploads\Jobs\DeleteUploadedObjectJob;
use App\Domain\EducationalResources\Models\EducationalResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class EducationalResourceDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_resource_dispatches_cleanup_for_each_path(): void
    {
        Bus::fake();

        $resource = EducationalResource::factory()->create([
            'thumbnail_path' => 'admin-uploads/abc/thumb.jpg',
            'media_path' => 'admin-uploads/abc/media.pdf',
        ]);

        $resource->delete();

        Bus::assertDispatched(
            DeleteUploadedObjectJob::class,
            fn (DeleteUploadedObjectJob $job): bool => $job->path === 'admin-uploads/abc/thumb.jpg',
        );

        Bus::assertDispatched(
            DeleteUploadedObjectJob::class,
            fn (DeleteUploadedObjectJob $job): bool => $job->path === 'admin-uploads/abc/media.pdf',
        );
    }

    public function test_resources_without_paths_dispatch_nothing(): void
    {
        Bus::fake();

        $resource = EducationalResource::factory()->create([
            'thumbnail_path' => null,
            'media_path' => null,
        ]);

        $resource->delete();

        Bus::assertNotDispatched(DeleteUploadedObjectJob::class);
    }

    public function test_only_paths_that_are_set_dispatch_jobs(): void
    {
        Bus::fake();

        $resource = EducationalResource::factory()->create([
            'thumbnail_path' => 'admin-uploads/xyz/thumb.png',
            'media_path' => null,
        ]);

        $resource->delete();

        Bus::assertDispatchedTimes(DeleteUploadedObjectJob::class, 1);
    }
}
