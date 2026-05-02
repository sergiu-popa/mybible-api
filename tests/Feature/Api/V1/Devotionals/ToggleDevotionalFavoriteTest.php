<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ToggleDevotionalFavoriteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_creates_a_favorite_when_none_exists(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $devotional = Devotional::factory()->create();

        $response = $this->postJson(
            route('devotional-favorites.toggle'),
            ['devotional_id' => $devotional->id],
        );

        $response
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'created_at', 'devotional' => ['id', 'title']]])
            ->assertJsonPath('data.devotional.id', $devotional->id);

        $this->assertDatabaseHas('devotional_favorites', [
            'user_id' => $user->id,
            'devotional_id' => $devotional->id,
        ]);
    }

    public function test_it_removes_an_existing_favorite(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $devotional = Devotional::factory()->create();
        DevotionalFavorite::factory()->forUser($user)->create(['devotional_id' => $devotional->id]);

        $this->postJson(
            route('devotional-favorites.toggle'),
            ['devotional_id' => $devotional->id],
        )
            ->assertOk()
            ->assertExactJson(['deleted' => true]);

        $this->assertSoftDeleted('devotional_favorites', [
            'user_id' => $user->id,
            'devotional_id' => $devotional->id,
        ]);
    }

    public function test_it_rejects_unknown_devotional_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this
            ->postJson(route('devotional-favorites.toggle'), ['devotional_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['devotional_id']);
    }

    public function test_it_does_not_affect_another_users_favorite(): void
    {
        $other = User::factory()->create();
        $devotional = Devotional::factory()->create();
        $otherFavorite = DevotionalFavorite::factory()->forUser($other)->create([
            'devotional_id' => $devotional->id,
        ]);

        $this->givenAnAuthenticatedUser();

        $this
            ->postJson(route('devotional-favorites.toggle'), ['devotional_id' => $devotional->id])
            ->assertCreated();

        $this->assertDatabaseHas('devotional_favorites', [
            'id' => $otherFavorite->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->postJson(route('devotional-favorites.toggle'), ['devotional_id' => 1])
            ->assertUnauthorized();
    }

    public function test_re_toggling_restores_the_same_primary_key(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $devotional = Devotional::factory()->create();

        $firstResponse = $this->postJson(
            route('devotional-favorites.toggle'),
            ['devotional_id' => $devotional->id],
        )->assertCreated();

        $originalId = $firstResponse->json('data.id');

        $this->postJson(
            route('devotional-favorites.toggle'),
            ['devotional_id' => $devotional->id],
        )->assertOk();

        $secondResponse = $this->postJson(
            route('devotional-favorites.toggle'),
            ['devotional_id' => $devotional->id],
        )->assertCreated();

        $this->assertSame($originalId, $secondResponse->json('data.id'));
        $this->assertDatabaseCount('devotional_favorites', 1);
    }
}
