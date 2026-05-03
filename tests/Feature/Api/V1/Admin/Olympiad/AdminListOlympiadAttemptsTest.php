<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminListOlympiadAttemptsTest extends TestCase
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

    public function test_it_requires_authentication(): void
    {
        $this->getJson(route('admin.olympiad.attempts.index'))
            ->assertUnauthorized();
    }

    public function test_it_forbids_non_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.olympiad.attempts.index'))
            ->assertForbidden();
    }

    public function test_it_lists_attempts_for_super_admin(): void
    {
        $this->actingAsSuper();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        OlympiadAttempt::factory()->forUser($userA)->create([
            'book' => 'GEN',
            'chapters_label' => '1-3',
            'language' => Language::Ro,
        ]);
        OlympiadAttempt::factory()->forUser($userB)->create([
            'book' => 'JHN',
            'chapters_label' => '5',
            'language' => Language::En,
        ]);

        $this->getJson(route('admin.olympiad.attempts.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'book', 'chapters_label', 'language', 'score', 'total', 'started_at', 'completed_at']],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_it_filters_by_user_id(): void
    {
        $this->actingAsSuper();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $mine = OlympiadAttempt::factory()->forUser($userA)->create();
        OlympiadAttempt::factory()->forUser($userB)->create();

        $this->getJson(route('admin.olympiad.attempts.index', ['user_id' => $userA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_it_filters_by_language_book_and_chapters(): void
    {
        $this->actingAsSuper();

        $user = User::factory()->create();

        $match = OlympiadAttempt::factory()->forUser($user)->create([
            'book' => 'GEN',
            'chapters_label' => '1-3',
            'language' => Language::Ro,
        ]);
        OlympiadAttempt::factory()->forUser($user)->create([
            'book' => 'JHN',
            'chapters_label' => '5',
            'language' => Language::En,
        ]);

        $this->getJson(route('admin.olympiad.attempts.index', [
            'language' => 'ro',
            'book' => 'GEN',
            'chapters' => '1-3',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }
}
