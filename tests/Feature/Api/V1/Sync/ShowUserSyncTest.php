<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Sync;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Notes\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ShowUserSyncTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson(route('sync.show'))
            ->assertUnauthorized();
    }

    public function test_full_sync_returns_all_expected_keys(): void
    {
        $this->givenAnAuthenticatedUser();

        $response = $this->getJson(route('sync.show'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'synced_at',
                    'next_since',
                    'favorites' => ['upserted', 'deleted'],
                    'notes' => ['upserted', 'deleted'],
                    'sabbath_school_answers' => ['upserted', 'deleted'],
                    'sabbath_school_highlights' => ['upserted', 'deleted'],
                    'sabbath_school_favorites' => ['upserted', 'deleted'],
                    'devotional_favorites' => ['upserted', 'deleted'],
                    'hymnal_favorites' => ['upserted', 'deleted'],
                ],
            ]);

        $this->assertNull($response->json('data.next_since'));
    }

    public function test_full_sync_returns_callers_favorites_in_upserted(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $favorite = Favorite::factory()->for($user)->create();

        $response = $this->getJson(route('sync.show'))
            ->assertOk();

        $upserted = $response->json('data.favorites.upserted');
        $deleted = $response->json('data.favorites.deleted');

        $this->assertCount(1, $upserted);
        $this->assertSame($favorite->id, $upserted[0]['id']);
        $this->assertCount(0, $deleted);
    }

    public function test_soft_deleted_rows_appear_in_deleted_array(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $favorite = Favorite::factory()->for($user)->create();
        $favorite->delete();

        $response = $this->getJson(route('sync.show'))
            ->assertOk();

        $upserted = $response->json('data.favorites.upserted');
        $deleted = $response->json('data.favorites.deleted');

        $this->assertCount(0, $upserted);
        $this->assertContains($favorite->id, $deleted);
    }

    public function test_delta_sync_excludes_records_older_than_since(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        Carbon::setTestNow('2025-01-01 10:00:00');
        $oldFavorite = Favorite::factory()->for($user)->create();

        Carbon::setTestNow('2025-06-01 10:00:00');
        $newFavorite = Favorite::factory()->for($user)->create();

        Carbon::setTestNow(null);

        $response = $this->getJson(
            route('sync.show', ['since' => '2025-03-01T00:00:00Z']),
        )->assertOk();

        $upserted = $response->json('data.favorites.upserted');
        $ids = array_column($upserted, 'id');

        $this->assertContains($newFavorite->id, $ids);
        $this->assertNotContains($oldFavorite->id, $ids);
    }

    public function test_missing_since_includes_all_records(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $favorite = Favorite::factory()->for($user)->create();

        $withoutSince = $this->getJson(route('sync.show'))->assertOk();

        $this->assertContains(
            $favorite->id,
            array_column($withoutSince->json('data.favorites.upserted'), 'id'),
        );
    }

    public function test_cross_user_records_are_excluded(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $otherUser = User::factory()->create();

        Favorite::factory()->for($otherUser)->create();
        $ownFavorite = Favorite::factory()->for($user)->create();

        $response = $this->getJson(route('sync.show'))->assertOk();

        $upserted = $response->json('data.favorites.upserted');
        $this->assertCount(1, $upserted);
        $this->assertSame($ownFavorite->id, $upserted[0]['id']);
    }

    public function test_next_since_is_emitted_when_a_builder_hits_the_cap(): void
    {
        config()->set('sync.per_type_cap', 2);

        $user = $this->givenAnAuthenticatedUser();

        Carbon::setTestNow('2025-01-01 10:00:00');
        Favorite::factory()->for($user)->create();
        Carbon::setTestNow('2025-01-01 11:00:00');
        Favorite::factory()->for($user)->create();
        Carbon::setTestNow('2025-01-01 12:00:00');
        Favorite::factory()->for($user)->create();
        Carbon::setTestNow(null);

        $response = $this->getJson(route('sync.show'))->assertOk();

        $this->assertNotNull($response->json('data.next_since'));
        $this->assertCount(2, $response->json('data.favorites.upserted'));
    }

    public function test_invalid_since_returns_422(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->getJson(route('sync.show', ['since' => 'not-a-date']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['since']);
    }

    public function test_next_since_is_null_when_no_builder_hits_the_cap(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        Favorite::factory()->for($user)->count(2)->create();
        Note::factory()->for($user)->count(2)->create();

        $response = $this->getJson(route('sync.show'))->assertOk();

        $this->assertNull($response->json('data.next_since'));
    }
}
