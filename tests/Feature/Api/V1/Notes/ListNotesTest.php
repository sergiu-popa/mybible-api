<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notes;

use App\Domain\Notes\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ListNotesTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_returns_only_notes_for_the_authenticated_user(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        Note::factory()->count(2)->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        Note::factory()->count(3)->create(['user_id' => $other->id]);

        $this->getJson(route('notes.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_orders_newest_first(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $older = Note::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::parse('2024-01-01 10:00:00'),
        ]);
        $newer = Note::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::parse('2024-06-01 10:00:00'),
        ]);

        $response = $this->getJson(route('notes.index'))->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_it_filters_by_book(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        Note::factory()->forBook('GEN')->create(['user_id' => $user->id]);
        Note::factory()->forBook('GEN')->create(['user_id' => $user->id]);
        Note::factory()->forBook('JHN')->create(['user_id' => $user->id]);

        $response = $this->getJson(route('notes.index', ['book' => 'GEN']))->assertOk();

        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $note) {
            $this->assertSame('GEN', $note['book']);
        }
    }

    public function test_it_rejects_an_invalid_book_filter(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->getJson(route('notes.index', ['book' => 'XXX']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('book');
    }

    public function test_it_paginates_with_configurable_per_page(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        Note::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson(route('notes.index', ['per_page' => 2]))->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson(route('notes.index'))->assertUnauthorized();
    }

    public function test_it_returns_note_resource_shape(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson(route('notes.index'))->assertOk();

        $response->assertJsonStructure([
            'data' => [
                ['id', 'reference', 'book', 'content', 'created_at', 'updated_at'],
            ],
            'meta',
            'links',
        ]);
        $this->assertSame($note->id, $response->json('data.0.id'));
    }
}
