<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad\Attempts;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StartOlympiadAttemptTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_it_starts_an_attempt_and_returns_question_uuids(): void
    {
        $this->actingAsUser();

        $q1 = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();
        $q2 = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();
        OlympiadAnswer::factory()->correct()->create(['olympiad_question_id' => $q1->id, 'position' => 1]);
        OlympiadAnswer::factory()->correct()->create(['olympiad_question_id' => $q2->id, 'position' => 1]);

        $response = $this->postJson(route('olympiad.attempts.store', ['language' => 'ro']), [
            'book' => 'GEN',
            'chapters' => '1-3',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'book', 'chapters_label', 'language', 'score', 'total', 'started_at', 'question_uuids'],
            ])
            ->assertJsonPath('data.book', 'GEN')
            ->assertJsonPath('data.chapters_label', '1-3')
            ->assertJsonPath('data.total', 2);

        $uuids = $response->json('data.question_uuids');
        $this->assertCount(2, (array) $uuids);
    }

    public function test_it_rejects_unauthenticated(): void
    {
        $this->postJson(route('olympiad.attempts.store'), [
            'book' => 'GEN',
            'chapters' => '1-3',
        ])->assertUnauthorized();
    }

    public function test_it_validates_chapters_segment(): void
    {
        $this->actingAsUser();

        $this->postJson(route('olympiad.attempts.store', ['language' => 'ro']), [
            'book' => 'GEN',
            'chapters' => 'not-a-range',
        ])->assertStatus(422);
    }
}
