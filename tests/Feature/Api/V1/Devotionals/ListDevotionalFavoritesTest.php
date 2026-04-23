<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ListDevotionalFavoritesTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_lists_the_authenticated_users_favorites_with_embedded_devotional(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $devotional = Devotional::factory()->create(['title' => 'Mine']);
        $favorite = DevotionalFavorite::factory()->forUser($user)->create(['devotional_id' => $devotional->id]);

        $response = $this->getJson(route('devotional-favorites.index'))->assertOk();

        $response
            ->assertJsonStructure([
                'data' => [['id', 'created_at', 'devotional' => ['id', 'date', 'type', 'language', 'title']]],
                'meta',
                'links',
            ])
            ->assertJsonPath('data.0.id', $favorite->id)
            ->assertJsonPath('data.0.devotional.id', $devotional->id)
            ->assertJsonPath('data.0.devotional.title', 'Mine');
    }

    public function test_it_scopes_favorites_to_the_caller(): void
    {
        $other = User::factory()->create();
        $devotional = Devotional::factory()->create();
        DevotionalFavorite::factory()->forUser($other)->create(['devotional_id' => $devotional->id]);

        $this->givenAnAuthenticatedUser();

        $this
            ->getJson(route('devotional-favorites.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_orders_newest_first(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $older = DevotionalFavorite::factory()->forUser($user)->create([
            'devotional_id' => Devotional::factory()->create()->id,
            'created_at' => now()->subDay(),
        ]);
        $newer = DevotionalFavorite::factory()->forUser($user)->create([
            'devotional_id' => Devotional::factory()->create()->id,
            'created_at' => now(),
        ]);

        $ids = array_column(
            $this->getJson(route('devotional-favorites.index'))->assertOk()->json('data'),
            'id',
        );

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->getJson(route('devotional-favorites.index'))
            ->assertUnauthorized();
    }
}
