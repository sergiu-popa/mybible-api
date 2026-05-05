<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed a row per supported ISO-2 language with NULL defaults. Idempotent —
 * pre-existing rows are left untouched so a re-run cannot clobber values
 * super-admins have already configured.
 */
return new class extends Migration
{
    private const LANGUAGES = ['ro', 'en', 'hu', 'es', 'fr', 'de', 'it'];

    public function up(): void
    {
        $now = now();

        $rows = array_map(
            static fn (string $language): array => [
                'language' => $language,
                'default_bible_version_id' => null,
                'default_commentary_id' => null,
                'default_devotional_type_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::LANGUAGES,
        );

        // upsert keyed on the unique `language` column so a re-run leaves
        // existing rows alone (empty update column list).
        DB::table('language_settings')->upsert($rows, ['language'], []);
    }

    public function down(): void
    {
        DB::table('language_settings')
            ->whereIn('language', self::LANGUAGES)
            ->delete();
    }
};
