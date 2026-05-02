<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end test for AC §22: legacy 3-char language codes and
 * long-form book values land in their canonical forms after the
 * backfill actions run, with `security_events` capturing unmapped
 * languages.
 */
final class IdentifierBackfillTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_rewrites_legacy_language_codes_on_users_table(): void
    {
        // Widen the column transiently so we can simulate the pre-MBA-023
        // prod shape (varchar(3)) where Symfony 3-char codes still live.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('language', 8)->nullable()->change();
        });

        $user = User::factory()->create(['language' => 'ro']);
        DB::table('users')->where('id', $user->id)->update(['language' => 'ron']);

        (new BackfillLegacyLanguageCodesAction)->handle('users', 'language');

        $this->assertSame('ro', DB::table('users')->where('id', $user->id)->value('language'));
    }

    public function test_it_rewrites_long_form_book_names_on_notes(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->string('book', 64)->change();
        });

        $user = User::factory()->create();

        $noteId = DB::table('notes')->insertGetId([
            'user_id' => $user->id,
            'reference' => 'GEN.1:1.VDC',
            'book' => 'Genesis',
            'content' => 'Test note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BackfillLegacyBookAbbreviationsAction)->handle('notes', 'book');

        $this->assertSame('GEN', DB::table('notes')->where('id', $noteId)->value('book'));
    }

    public function test_unknown_language_codes_are_logged_to_security_events(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('language', 8)->nullable()->change();
        });

        $user = User::factory()->create(['language' => 'ro']);
        DB::table('users')->where('id', $user->id)->update(['language' => 'zzz']);

        (new BackfillLegacyLanguageCodesAction)->handle('users', 'language');

        $this->assertSame('ro', DB::table('users')->where('id', $user->id)->value('language'));

        $event = DB::table('security_events')->where('event', 'language_backfill_default')->first();
        $this->assertNotNull($event);
    }
}
