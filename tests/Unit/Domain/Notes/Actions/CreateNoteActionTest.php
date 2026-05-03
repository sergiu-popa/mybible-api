<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Notes\Actions;

use App\Domain\Notes\Actions\CreateNoteAction;
use App\Domain\Notes\DataTransferObjects\CreateNoteData;
use App\Domain\Reference\Reference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateNoteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_note_for_the_user(): void
    {
        $user = User::factory()->create();
        $reference = new Reference('GEN', 1, [1], 'VDC');

        $data = new CreateNoteData(
            user: $user,
            reference: $reference,
            canonicalReference: 'GEN.1:1.VDC',
            content: 'In the beginning.',
            color: null,
        );

        $note = $this->app->make(CreateNoteAction::class)->execute($data);

        $this->assertSame($user->id, $note->user_id);
        $this->assertSame('GEN.1:1.VDC', $note->reference);
        $this->assertSame('GEN', $note->book);
        $this->assertSame('In the beginning.', $note->content);
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'user_id' => $user->id,
            'reference' => 'GEN.1:1.VDC',
            'book' => 'GEN',
        ]);
    }
}
