<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Uploads;

use App\Domain\Admin\Uploads\Actions\IssuePresignedUploadAction;
use App\Domain\Admin\Uploads\DataTransferObjects\PresignedUploadRequest;
use App\Domain\Admin\Uploads\DataTransferObjects\PresignedUploadResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class IssuePresignedUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a stubbed action so the test does not need a live S3 disk;
        // the unit boundary is the controller + form request, while the
        // S3 wiring is exercised by IssuePresignedUploadActionTest.
        $this->app->bind(IssuePresignedUploadAction::class, fn () => new class extends IssuePresignedUploadAction
        {
            public function execute(PresignedUploadRequest $request): PresignedUploadResult
            {
                return new PresignedUploadResult(
                    key: 'admin-uploads/test-uuid/' . $request->filename,
                    uploadUrl: 'https://s3.example.test/admin-uploads/test-uuid/' . $request->filename . '?sig=fake',
                    expiresAt: Carbon::instance(Date::create(2026, 4, 30, 12, 10, 0)),
                    headers: [
                        'Content-Type' => $request->contentType,
                        'Content-Length' => (string) $request->sizeBytes,
                    ],
                );
            }
        });
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_it_returns_a_presigned_url_for_an_allowed_payload(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.uploads.store'), [
            'filename' => 'hero.jpg',
            'content_type' => 'image/jpeg',
            'size' => 1024 * 512,
        ])
            ->assertCreated()
            ->assertJsonPath('key', 'admin-uploads/test-uuid/hero.jpg')
            ->assertJsonPath('headers.Content-Type', 'image/jpeg')
            ->assertJsonStructure(['key', 'upload_url', 'expires_at', 'headers']);
    }

    public function test_it_rejects_disallowed_content_types(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.uploads.store'), [
            'filename' => 'evil.exe',
            'content_type' => 'application/x-msdownload',
            'size' => 1024,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content_type']);
    }

    public function test_it_rejects_oversized_uploads(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.uploads.store'), [
            'filename' => 'huge.zip',
            'content_type' => 'application/zip',
            'size' => 200 * 1024 * 1024,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size']);
    }

    public function test_it_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.uploads.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filename', 'content_type', 'size']);
    }

    public function test_it_blocks_non_admin_users(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.uploads.store'), [
                'filename' => 'hero.jpg',
                'content_type' => 'image/jpeg',
                'size' => 1024,
            ])
            ->assertForbidden();
    }
}
