<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Hymnal\Actions;

use App\Domain\Hymnal\Actions\ToggleHymnalFavoriteAction;
use App\Domain\Hymnal\DataTransferObjects\ToggleHymnalFavoriteData;
use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ToggleHymnalFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_favorite_when_none_exists(): void
    {
        $user = User::factory()->create();
        $song = HymnalSong::factory()->create();

        $result = $this->app->make(ToggleHymnalFavoriteAction::class)
            ->execute(new ToggleHymnalFavoriteData($user, $song));

        $this->assertTrue($result->created);
        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);
        $this->assertSame($user->id, $result->favorite->user_id);
        $this->assertSame($song->id, $result->favorite->hymnal_song_id);
    }

    public function test_it_deletes_an_existing_favorite(): void
    {
        $user = User::factory()->create();
        $song = HymnalSong::factory()->create();
        $existing = HymnalFavorite::factory()->create([
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);

        $result = $this->app->make(ToggleHymnalFavoriteAction::class)
            ->execute(new ToggleHymnalFavoriteData($user, $song));

        $this->assertFalse($result->created);
        $this->assertDatabaseMissing('hymnal_favorites', ['id' => $existing->id]);
    }

    public function test_it_rolls_back_the_insert_when_the_transaction_body_throws(): void
    {
        $user = User::factory()->create();
        $song = HymnalSong::factory()->create();

        HymnalFavorite::created(function (): void {
            throw new RuntimeException('downstream failure');
        });

        try {
            $this->app->make(ToggleHymnalFavoriteAction::class)
                ->execute(new ToggleHymnalFavoriteData($user, $song));
            $this->fail('Expected RuntimeException to bubble out of the transaction.');
        } catch (RuntimeException $e) {
            $this->assertSame('downstream failure', $e->getMessage());
        } finally {
            HymnalFavorite::flushEventListeners();
        }

        $this->assertDatabaseMissing('hymnal_favorites', [
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);
    }

    public function test_it_rolls_back_the_delete_when_the_transaction_body_throws(): void
    {
        $user = User::factory()->create();
        $song = HymnalSong::factory()->create();
        $existing = HymnalFavorite::factory()->create([
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);

        HymnalFavorite::deleted(function (): void {
            throw new RuntimeException('downstream failure');
        });

        try {
            $this->app->make(ToggleHymnalFavoriteAction::class)
                ->execute(new ToggleHymnalFavoriteData($user, $song));
            $this->fail('Expected RuntimeException to bubble out of the transaction.');
        } catch (RuntimeException $e) {
            $this->assertSame('downstream failure', $e->getMessage());
        } finally {
            HymnalFavorite::flushEventListeners();
        }

        $this->assertDatabaseHas('hymnal_favorites', [
            'id' => $existing->id,
            'user_id' => $user->id,
            'hymnal_song_id' => $song->id,
        ]);
    }

    public function test_it_keeps_other_users_favorites_untouched(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $song = HymnalSong::factory()->create();

        HymnalFavorite::factory()->create(['user_id' => $alice->id, 'hymnal_song_id' => $song->id]);

        $this->app->make(ToggleHymnalFavoriteAction::class)
            ->execute(new ToggleHymnalFavoriteData($bob, $song));

        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $alice->id,
            'hymnal_song_id' => $song->id,
        ]);
        $this->assertDatabaseHas('hymnal_favorites', [
            'user_id' => $bob->id,
            'hymnal_song_id' => $song->id,
        ]);
    }
}
