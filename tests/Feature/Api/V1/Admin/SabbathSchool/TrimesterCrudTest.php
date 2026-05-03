<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrimesterCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson(route('admin.sabbath-school.trimesters.index'))->assertUnauthorized();
    }

    public function test_index_requires_admin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.sabbath-school.trimesters.index'))
            ->assertForbidden();
    }

    public function test_index_returns_trimesters(): void
    {
        $this->actingAsAdmin();
        $trimester = SabbathSchoolTrimester::factory()->create();

        $this->getJson(route('admin.sabbath-school.trimesters.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $trimester->id);
    }

    public function test_create_validates_payload(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.sabbath-school.trimesters.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'language', 'age_group', 'title', 'number', 'date_from', 'date_to']);
    }

    public function test_create_persists_trimester(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'year' => '2026',
            'language' => Language::En->value,
            'age_group' => 'adult',
            'title' => 'Q1 2026',
            'number' => 1,
            'date_from' => '2026-01-01',
            'date_to' => '2026-03-31',
            'image_cdn_url' => null,
        ];

        $this->postJson(route('admin.sabbath-school.trimesters.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Q1 2026');

        $this->assertDatabaseHas('sabbath_school_trimesters', [
            'year' => '2026',
            'language' => 'en',
            'title' => 'Q1 2026',
            'number' => 1,
        ]);
    }

    public function test_update_persists_changes(): void
    {
        $this->actingAsAdmin();
        $trimester = SabbathSchoolTrimester::factory()->create(['title' => 'Old']);

        $this->patchJson(
            route('admin.sabbath-school.trimesters.update', ['trimester' => $trimester->id]),
            ['title' => 'New title'],
        )
            ->assertOk()
            ->assertJsonPath('data.title', 'New title');

        $this->assertSame('New title', $trimester->refresh()->title);
    }

    public function test_destroy_deletes_trimester(): void
    {
        $this->actingAsAdmin();
        $trimester = SabbathSchoolTrimester::factory()->create();

        $this->deleteJson(route('admin.sabbath-school.trimesters.destroy', ['trimester' => $trimester->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('sabbath_school_trimesters', ['id' => $trimester->id]);
    }
}
