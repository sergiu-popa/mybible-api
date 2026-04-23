<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class FavoriteReferenceResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_parsed_reference_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('favorites.store'), [
            'reference' => 'GEN.1:1-3.VDC',
        ])->assertCreated();

        $response
            ->assertJsonPath('data.book', 'GEN')
            ->assertJsonPath('data.chapter', 1)
            ->assertJsonPath('data.verses', [1, 2, 3])
            ->assertJsonPath('data.version', 'VDC');

        $humanReadable = $response->json('data.human_readable');
        $this->assertIsString($humanReadable);
        $this->assertNotSame('', $humanReadable);
    }

    public function test_it_renders_human_readable_in_requested_language(): void
    {
        $user = User::factory()->create();
        $favorite = Favorite::factory()->for($user)->create([
            'reference' => 'GEN.1:1-3.VDC',
        ]);

        Sanctum::actingAs($user);

        $en = $this->getJson(route('favorites.index', ['language' => 'en']))
            ->assertOk()
            ->json('data.0.human_readable');

        $ro = $this->getJson(route('favorites.index', ['language' => 'ro']))
            ->assertOk()
            ->json('data.0.human_readable');

        // Same favorite id across both calls.
        $this->assertSame($favorite->id, $this->getJson(route('favorites.index', ['language' => 'en']))->json('data.0.id'));

        $this->assertIsString($en);
        $this->assertIsString($ro);
        // Romanian book names differ from English — ensure the request-scoped
        // language is consulted when rendering.
        $this->assertNotSame($en, $ro);
    }

    public function test_it_exposes_a_whole_chapter_reference(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorites.store'), [
            'reference' => 'PSA.23.VDC',
        ])
            ->assertCreated()
            ->assertJsonPath('data.book', 'PSA')
            ->assertJsonPath('data.chapter', 23)
            ->assertJsonPath('data.verses', [])
            ->assertJsonPath('data.version', 'VDC');
    }
}
