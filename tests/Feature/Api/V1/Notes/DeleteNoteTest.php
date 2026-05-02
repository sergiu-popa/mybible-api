<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notes;

use App\Domain\Notes\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class DeleteNoteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_owner_can_delete_note(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->deleteJson(route('notes.destroy', ['note' => $note->id]))
            ->assertNoContent();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_cross_user_access_returns_403_and_keeps_note(): void
    {
        $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create();

        $this->deleteJson(route('notes.destroy', ['note' => $note->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }

    public function test_it_returns_404_for_unknown_note(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->deleteJson(route('notes.destroy', ['note' => 999999]))
            ->assertNotFound();
    }

    public function test_it_requires_authentication(): void
    {
        $note = Note::factory()->create();

        $this->deleteJson(route('notes.destroy', ['note' => $note->id]))
            ->assertUnauthorized();

        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }
}
