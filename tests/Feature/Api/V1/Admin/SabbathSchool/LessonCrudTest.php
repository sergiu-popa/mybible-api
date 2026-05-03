<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LessonCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_index_requires_admin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.sabbath-school.lessons.index'))
            ->assertForbidden();
    }

    public function test_index_returns_lessons_including_drafts(): void
    {
        $this->actingAsAdmin();
        $draft = SabbathSchoolLesson::factory()->draft()->create();
        $published = SabbathSchoolLesson::factory()->published()->create();

        $this->getJson(route('admin.sabbath-school.lessons.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_validates_payload(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.sabbath-school.lessons.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language', 'age_group', 'title', 'number', 'date_from', 'date_to']);
    }

    public function test_create_persists_lesson_with_new_fields(): void
    {
        $this->actingAsAdmin();
        $trimester = SabbathSchoolTrimester::factory()->create();

        $payload = [
            'language' => Language::En->value,
            'age_group' => 'youth',
            'title' => 'Lesson 1',
            'number' => 1,
            'date_from' => '2026-01-04',
            'date_to' => '2026-01-10',
            'trimester_id' => $trimester->id,
            'memory_verse' => 'Genesis 1:1',
            'image_cdn_url' => 'https://cdn.example.com/lesson1.jpg',
            'published_at' => '2025-12-31 00:00:00',
        ];

        $this->postJson(route('admin.sabbath-school.lessons.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.age_group', 'youth')
            ->assertJsonPath('data.memory_verse', 'Genesis 1:1')
            ->assertJsonPath('data.image_cdn_url', 'https://cdn.example.com/lesson1.jpg')
            ->assertJsonPath('data.trimester_id', $trimester->id);

        $this->assertDatabaseHas('sabbath_school_lessons', [
            'title' => 'Lesson 1',
            'age_group' => 'youth',
            'trimester_id' => $trimester->id,
        ]);
    }

    public function test_update_persists_changes(): void
    {
        $this->actingAsAdmin();
        $lesson = SabbathSchoolLesson::factory()->create(['title' => 'Old']);

        $this->patchJson(
            route('admin.sabbath-school.lessons.update', ['lesson' => $lesson->id]),
            ['title' => 'New title'],
        )->assertOk()->assertJsonPath('data.title', 'New title');

        $this->assertSame('New title', $lesson->refresh()->title);
    }

    public function test_destroy_deletes_lesson(): void
    {
        $this->actingAsAdmin();
        $lesson = SabbathSchoolLesson::factory()->create();

        $this->deleteJson(route('admin.sabbath-school.lessons.destroy', ['lesson' => $lesson->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('sabbath_school_lessons', ['id' => $lesson->id]);
    }

    public function test_admin_lesson_binding_serves_drafts(): void
    {
        $this->actingAsAdmin();
        $lesson = SabbathSchoolLesson::factory()->draft()->create();

        $this->patchJson(
            route('admin.sabbath-school.lessons.update', ['lesson' => $lesson->id]),
            ['title' => 'Renamed'],
        )->assertOk();
    }
}
