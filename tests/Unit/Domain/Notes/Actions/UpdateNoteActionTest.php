<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Notes\Actions;

use App\Domain\Notes\Actions\UpdateNoteAction;
use App\Domain\Notes\DataTransferObjects\UpdateNoteData;
use App\Domain\Notes\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateNoteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_content_and_preserves_reference(): void
    {
        $note = Note::factory()->create([
            'reference' => 'GEN.1:1.VDC',
            'book' => 'GEN',
            'content' => 'Original.',
        ]);

        $result = $this->app->make(UpdateNoteAction::class)->execute(
            new UpdateNoteData(note: $note, content: 'Updated.', color: null, colorProvided: false),
        );

        $this->assertSame('Updated.', $result->content);
        $this->assertSame('GEN.1:1.VDC', $result->reference);
        $this->assertSame('GEN', $result->book);
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated.',
            'reference' => 'GEN.1:1.VDC',
        ]);
    }
}
