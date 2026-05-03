<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowSabbathSchoolTrimesterTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_trimester_detail_with_nested_lessons(): void
    {
        $trimester = SabbathSchoolTrimester::factory()->create();
        $lesson = SabbathSchoolLesson::factory()
            ->forTrimester($trimester)
            ->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.trimesters.show', ['trimester' => $trimester->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $trimester->id)
            ->assertJsonPath('data.lessons.0.id', $lesson->id);
    }

    public function test_it_returns_404_for_unknown_trimester(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.trimesters.show', ['trimester' => 999_999]))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $trimester = SabbathSchoolTrimester::factory()->create();

        $this->getJson(route('sabbath-school.trimesters.show', ['trimester' => $trimester->id]))
            ->assertUnauthorized();
    }
}
