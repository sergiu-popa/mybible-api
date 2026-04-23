<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ListHymnalFavoritesTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_returns_only_the_authenticated_users_favorites(): void
    {
        $alice = $this->givenAnAuthenticatedUser();
        $bob = User::factory()->create();

        $song = HymnalSong::factory()->create();
        HymnalFavorite::factory()->create(['user_id' => $alice->id, 'hymnal_song_id' => $song->id]);
        HymnalFavorite::factory()->create(['user_id' => $bob->id]);

        $this->getJson(route('hymnal-favorites.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.song.id', $song->id);
    }

    public function test_it_embeds_the_full_song_payload(): void
    {
        $alice = $this->givenAnAuthenticatedUser();
        $song = HymnalSong::factory()->create([
            'title' => ['en' => 'Hymn Title'],
        ]);
        HymnalFavorite::factory()->create(['user_id' => $alice->id, 'hymnal_song_id' => $song->id]);

        $this->getJson(route('hymnal-favorites.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'created_at',
                    'song' => ['id', 'number', 'title', 'stanzas', 'book'],
                ]],
            ])
            ->assertJsonPath('data.0.song.title', 'Hymn Title');
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        $this->getJson(route('hymnal-favorites.index'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_api_key_only(): void
    {
        // api-key-or-sanctum does not protect this route — only sanctum does.
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('hymnal-favorites.index'))
            ->assertUnauthorized();
    }
}
