<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ToggleHymnalFavoriteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_creates_a_favorite_on_first_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $song = HymnalSong::factory()->create();

        $this->postJson(route('hymnal-favorites.toggle'), ['song_id' => $song->id])
            ->assertCreated()
            ->assertJsonPath('data.song.id', $song->id);

        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);
    }

    public function test_it_deletes_the_favorite_on_second_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $song = HymnalSong::factory()->create();
        HymnalFavorite::factory()->create(['user_id' => $user->id, 'hymnal_song_id' => $song->id]);

        $this->postJson(route('hymnal-favorites.toggle'), ['song_id' => $song->id])
            ->assertOk()
            ->assertExactJson(['deleted' => true]);

        $this->assertDatabaseMissing('hymnal_favorites', [
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);
    }

    public function test_it_validates_unknown_song_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('hymnal-favorites.toggle'), ['song_id' => 999_999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_it_validates_missing_song_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('hymnal-favorites.toggle'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_it_allows_two_users_to_favorite_the_same_song(): void
    {
        $song = HymnalSong::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        HymnalFavorite::factory()->create(['user_id' => $alice->id, 'hymnal_song_id' => $song->id]);

        Sanctum::actingAs($bob);

        $this->postJson(route('hymnal-favorites.toggle'), ['song_id' => $song->id])
            ->assertCreated();

        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $alice->id,
            'hymnal_song_id' => $song->id,
        ]);
        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $bob->id,
            'hymnal_song_id' => $song->id,
        ]);
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        $song = HymnalSong::factory()->create();

        $this->postJson(route('hymnal-favorites.toggle'), ['song_id' => $song->id])
            ->assertUnauthorized();
    }
}
