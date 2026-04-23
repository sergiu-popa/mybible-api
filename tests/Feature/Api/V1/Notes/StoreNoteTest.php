<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notes;

use App\Http\Requests\Notes\StoreNoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class StoreNoteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_creates_a_note_and_returns_201_with_canonical_reference(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $response = $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1:1.VDC',
            'content' => 'In the beginning God created.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reference', 'GEN.1:1.VDC')
            ->assertJsonPath('data.book', 'GEN')
            ->assertJsonPath('data.content', 'In the beginning God created.');

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'reference' => 'GEN.1:1.VDC',
            'book' => 'GEN',
            'content' => 'In the beginning God created.',
        ]);
    }

    public function test_it_rejects_an_invalid_reference(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('notes.store'), [
            'reference' => 'not a reference',
            'content' => 'hello',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_it_rejects_a_multi_reference_input(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1-3.VDC',
            'content' => 'hello',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reference');

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_it_rejects_missing_reference(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('notes.store'), ['content' => 'hello'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_it_rejects_missing_content(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('notes.store'), ['reference' => 'GEN.1:1.VDC'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_it_rejects_content_exceeding_the_max_length(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1:1.VDC',
            'content' => str_repeat('a', StoreNoteRequest::CONTENT_MAX_LENGTH + 1),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_it_strips_html_and_measures_length_post_strip(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        // Text is `hi ` + 9998 `a`s inside <b></b>, total < 10 001 chars stripped
        // but > 10 000 pre-strip. It should pass because stripping yields
        // exactly 10 000 chars.
        $stripped = 'hi ' . str_repeat('a', StoreNoteRequest::CONTENT_MAX_LENGTH - 3);
        $raw = 'hi <b>' . str_repeat('a', StoreNoteRequest::CONTENT_MAX_LENGTH - 3) . '</b>';

        $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1:1.VDC',
            'content' => $raw,
        ])->assertCreated()
            ->assertJsonPath('data.content', $stripped);

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'content' => $stripped,
        ]);
    }

    public function test_it_rejects_content_that_still_exceeds_max_after_stripping(): void
    {
        $this->givenAnAuthenticatedUser();

        $raw = str_repeat('a', StoreNoteRequest::CONTENT_MAX_LENGTH + 1) . '<b>x</b>';

        $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1:1.VDC',
            'content' => $raw,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(route('notes.store'), [
            'reference' => 'GEN.1:1.VDC',
            'content' => 'hello',
        ])->assertUnauthorized();
    }
}
