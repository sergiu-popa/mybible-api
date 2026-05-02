<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Notes\Actions;

use App\Domain\Notes\Actions\DeleteNoteAction;
use App\Domain\Notes\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteNoteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_the_note_from_the_database(): void
    {
        $note = Note::factory()->create();

        $this->app->make(DeleteNoteAction::class)->execute($note);

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }
}
