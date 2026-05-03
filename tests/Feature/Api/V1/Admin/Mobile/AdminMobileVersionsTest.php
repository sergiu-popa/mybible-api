<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Mobile;

use App\Domain\Mobile\Models\MobileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminMobileVersionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_requires_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.mobile-versions.index'))
            ->assertForbidden();
    }

    public function test_list_returns_seeded_rows(): void
    {
        $this->actingAsSuper();

        MobileVersion::query()->delete();
        MobileVersion::factory()->ios()->latest()->create(['version' => '3.4.1']);
        MobileVersion::factory()->android()->minRequired()->create(['version' => '3.0.0']);

        $this->getJson(route('admin.mobile-versions.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'platform', 'kind', 'version', 'released_at', 'release_notes', 'store_url']],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_create_persists_a_new_row(): void
    {
        $this->actingAsSuper();

        MobileVersion::query()->where(['platform' => 'ios', 'kind' => MobileVersion::KIND_LATEST])->delete();

        $this->postJson(route('admin.mobile-versions.store'), [
            'platform' => 'ios',
            'kind' => 'latest',
            'version' => '3.5.0',
            'store_url' => 'https://apps.apple.com/app/id1',
            'release_notes' => ['en' => 'Bug fixes'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.kind', 'latest')
            ->assertJsonPath('data.version', '3.5.0');

        $this->assertDatabaseHas('mobile_versions', [
            'platform' => 'ios',
            'kind' => 'latest',
            'version' => '3.5.0',
        ]);
    }

    public function test_create_rejects_duplicate_platform_kind(): void
    {
        $this->actingAsSuper();

        // After migration seed, (ios, latest) likely exists. Ensure it does
        // and then re-create.
        MobileVersion::query()->updateOrCreate(
            ['platform' => 'ios', 'kind' => MobileVersion::KIND_LATEST],
            ['version' => '3.4.1'],
        );

        $this->postJson(route('admin.mobile-versions.store'), [
            'platform' => 'ios',
            'kind' => 'latest',
            'version' => '3.5.0',
        ])->assertStatus(500);
    }

    public function test_create_rejects_invalid_version_format(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.mobile-versions.store'), [
            'platform' => 'ios',
            'kind' => 'latest',
            'version' => 'not-a-version',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_update_changes_fields(): void
    {
        $this->actingAsSuper();

        MobileVersion::query()->where(['platform' => 'ios', 'kind' => MobileVersion::KIND_LATEST])->delete();
        $row = MobileVersion::factory()->ios()->latest()->create(['version' => '3.4.1']);

        $this->patchJson(route('admin.mobile-versions.update', ['version' => $row->id]), [
            'version' => '3.5.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.version', '3.5.0');
    }

    public function test_update_rejects_duplicate_platform_kind_with_422(): void
    {
        $this->actingAsSuper();

        MobileVersion::query()
            ->whereIn('kind', [MobileVersion::KIND_LATEST, MobileVersion::KIND_MIN_REQUIRED])
            ->where('platform', 'android')
            ->delete();

        MobileVersion::factory()->android()->latest()->create(['version' => '3.4.1']);
        $other = MobileVersion::factory()->android()->minRequired()->create(['version' => '3.0.0']);

        $this->patchJson(route('admin.mobile-versions.update', ['version' => $other->id]), [
            'kind' => 'latest',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kind']);
    }

    public function test_destroy_removes_row(): void
    {
        $this->actingAsSuper();

        MobileVersion::query()->where(['platform' => 'ios', 'kind' => MobileVersion::KIND_MIN_REQUIRED])->delete();
        $row = MobileVersion::factory()->ios()->minRequired()->create(['version' => '3.0.0']);

        $this->deleteJson(route('admin.mobile-versions.destroy', ['version' => $row->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('mobile_versions', ['id' => $row->id]);
    }
}
