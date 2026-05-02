<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Migration\Actions;

use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BackfillLegacyLanguageCodesActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('language_backfill_fixture', function (Blueprint $table): void {
            $table->id();
            $table->string('language', 8);
        });
    }

    public function test_it_rewrites_three_char_codes_to_two_char(): void
    {
        $id = DB::table('language_backfill_fixture')->insertGetId(['language' => 'ron']);

        (new BackfillLegacyLanguageCodesAction)->handle('language_backfill_fixture', 'language');

        $this->assertSame('ro', DB::table('language_backfill_fixture')->where('id', $id)->value('language'));
    }

    public function test_it_is_idempotent_for_already_two_char_values(): void
    {
        $id = DB::table('language_backfill_fixture')->insertGetId(['language' => 'en']);

        (new BackfillLegacyLanguageCodesAction)->handle('language_backfill_fixture', 'language');

        $this->assertSame('en', DB::table('language_backfill_fixture')->where('id', $id)->value('language'));
    }

    public function test_it_defaults_unknown_codes_and_logs_a_security_event(): void
    {
        $id = DB::table('language_backfill_fixture')->insertGetId(['language' => 'zzz']);

        (new BackfillLegacyLanguageCodesAction)->handle('language_backfill_fixture', 'language');

        $this->assertSame('ro', DB::table('language_backfill_fixture')->where('id', $id)->value('language'));

        $event = DB::table('security_events')->where('event', 'language_backfill_default')->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->metadata);

        /** @var array{original_code: string, table: string, column: string, row_id: int} $metadata */
        $metadata = json_decode((string) $event->metadata, true);

        $this->assertSame('zzz', $metadata['original_code']);
        $this->assertSame('language_backfill_fixture', $metadata['table']);
        $this->assertSame('language', $metadata['column']);
        $this->assertSame($id, (int) $metadata['row_id']);
    }
}
