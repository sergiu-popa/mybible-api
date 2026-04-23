<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notes;

use App\Domain\Notes\Models\Note;
use App\Http\Requests\Notes\UpdateNoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class UpdateNoteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_owner_can_update_content(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create([
            'user_id' => $user->id,
            'content' => 'Original.',
        ]);

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => 'Updated.',
        ])->assertOk()
            ->assertJsonPath('data.content', 'Updated.')
            ->assertJsonPath('data.reference', $note->reference);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated.',
        ]);
    }

    public function test_reference_field_in_body_is_silently_ignored(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create([
            'user_id' => $user->id,
            'reference' => 'GEN.1:1.VDC',
            'book' => 'GEN',
            'content' => 'Original.',
        ]);

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => 'Updated.',
            'reference' => 'JHN.3:16.VDC',
        ])->assertOk();

        $fresh = $note->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('GEN.1:1.VDC', $fresh->reference);
        $this->assertSame('GEN', $fresh->book);
        $this->assertSame('Updated.', $fresh->content);
    }

    public function test_cross_user_access_returns_403(): void
    {
        $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create();

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => 'Hacker.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
            'content' => 'Hacker.',
        ]);
    }

    public function test_it_rejects_content_exceeding_max_length(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => str_repeat('a', UpdateNoteRequest::CONTENT_MAX_LENGTH + 1),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_it_strips_html_from_content(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => '<b>bold</b> text',
        ])->assertOk()
            ->assertJsonPath('data.content', 'bold text');
    }

    public function test_it_rejects_missing_content(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->patchJson(route('notes.update', ['note' => $note->id]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_it_returns_404_for_unknown_note(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->patchJson(route('notes.update', ['note' => 999999]), [
            'content' => 'hi',
        ])->assertNotFound();
    }

    public function test_it_requires_authentication(): void
    {
        $note = Note::factory()->create();

        $this->patchJson(route('notes.update', ['note' => $note->id]), [
            'content' => 'hi',
        ])->assertUnauthorized();
    }
}
