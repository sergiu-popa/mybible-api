<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad\Attempts;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListUserOlympiadAttemptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_the_authenticated_users_attempts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        OlympiadAttempt::factory()->forUser($user)->create();
        OlympiadAttempt::factory()->forUser($user)->create();
        OlympiadAttempt::factory()->forUser($other)->create();

        $this->getJson(route('olympiad.attempts.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'book', 'chapters_label', 'language', 'score', 'total', 'started_at']],
                'meta' => ['per_page', 'current_page', 'total'],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_it_filters_by_book_chapters_language(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        OlympiadAttempt::factory()->forUser($user)->create([
            'book' => 'GEN', 'chapters_label' => '1-3', 'language' => Language::Ro,
        ]);
        OlympiadAttempt::factory()->forUser($user)->create([
            'book' => 'JHN', 'chapters_label' => '1-3', 'language' => Language::Ro,
        ]);

        $this->getJson(route('olympiad.attempts.index', ['book' => 'GEN']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.book', 'GEN');
    }

    public function test_it_rejects_unauthenticated(): void
    {
        $this->getJson(route('olympiad.attempts.index'))->assertUnauthorized();
    }
}
