<?php

declare(strict_types=1);

use App\Domain\Reference\Data\BibleBookCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile Symfony's `bible` / `book` / `verse` tables with the Laravel
 * naming and global-book-row layout. Idempotent: gated on the legacy
 * `bible` table, so fresh CI/dev passes through. Production cutover
 * (MBA-031) seeds the per-version `book` row data into a global
 * `bible_books` table while preserving the per-version mapping in
 * `_legacy_book_map` for the row-level verse ETL that follows.
 *
 * The `_legacy_book_map` table is consumed by MBA-031 ETL to backfill
 * `bible_verses.bible_book_id` / `bible_versions.bible_version_id` and
 * is dropped by MBA-032 cleanup. Do not delete it as orphaned scaffolding.
 *
 * Tie-break for diverging book metadata across legacy `bible` rows: the
 * row whose Bible's abbreviation is `VDC` wins; otherwise the lowest
 * legacy `book.id`. Localised per-version names are not preserved here
 * (they move to JSON columns later via MBA-024/MBA-027).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bible')) {
            return;
        }

        $this->reconcileBibleTable();
        $this->reconcileBookTables();
        $this->reconcileVerseTable();
    }

    public function down(): void
    {
        if (Schema::hasTable('bible_verses') && Schema::hasColumn('bible_verses', 'bible_id')) {
            Schema::rename('bible_verses', 'verse');
        }

        if (Schema::hasTable('bible_versions') && ! Schema::hasTable('bible')) {
            Schema::rename('bible_versions', 'bible');
        }

        Schema::dropIfExists('_legacy_book_map');
    }

    private function reconcileBibleTable(): void
    {
        // The create_bible_versions_table migration that ran before us may
        // have created an empty `bible_versions` against a schema that
        // already had Symfony's `bible`. Drop the empty Laravel-shaped
        // table so the rename below preserves the live data.
        if (Schema::hasTable('bible_versions') && DB::table('bible_versions')->count() === 0) {
            Schema::drop('bible_versions');
        }

        if (! Schema::hasTable('bible_versions')) {
            Schema::rename('bible', 'bible_versions');
        }

        Schema::table('bible_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('bible_versions', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('bible_versions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    private function reconcileBookTables(): void
    {
        if (! Schema::hasTable('book')) {
            return;
        }

        if (! Schema::hasTable('_legacy_book_map')) {
            Schema::create('_legacy_book_map', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_book_id')->primary();
                $table->unsignedBigInteger('legacy_bible_id')->nullable();
                $table->unsignedBigInteger('bible_book_id');
                $table->unsignedBigInteger('bible_version_id')->nullable();

                $table->index(['legacy_bible_id', 'legacy_book_id']);
            });
        }

        if (Schema::hasTable('bible_books') && DB::table('bible_books')->count() === 0) {
            Schema::drop('bible_books');
        }

        if (! Schema::hasTable('bible_books')) {
            Schema::create('bible_books', function (Blueprint $table): void {
                $table->id();
                $table->string('abbreviation', 8)->unique();
                $table->string('testament', 8)->nullable();
                $table->unsignedSmallInteger('position')->nullable();
                $table->unsignedSmallInteger('chapter_count')->nullable();
                $table->json('names')->nullable();
                $table->json('short_names')->nullable();
                $table->timestamps();
            });
        }

        $abbrevMap = $this->loadAbbreviationMap();

        $rows = DB::table('book')->orderBy('id')->get();

        foreach ($rows as $row) {
            $rawAbbreviation = (string) ($row->abbreviation ?? '');
            $usfm = $this->resolveUsfm($rawAbbreviation, (string) ($row->name ?? ''), $abbrevMap);

            if ($usfm === null) {
                continue;
            }

            $globalId = DB::table('bible_books')->where('abbreviation', $usfm)->value('id');

            if ($globalId === null) {
                $globalId = DB::table('bible_books')->insertGetId([
                    'abbreviation' => $usfm,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('_legacy_book_map')->updateOrInsert(
                ['legacy_book_id' => $row->id],
                [
                    'legacy_bible_id' => $row->bible_id ?? null,
                    'bible_book_id' => $globalId,
                    'bible_version_id' => $row->bible_id ?? null,
                ],
            );
        }

        Schema::drop('book');
    }

    private function reconcileVerseTable(): void
    {
        if (! Schema::hasTable('verse')) {
            return;
        }

        if (Schema::hasTable('bible_verses') && DB::table('bible_verses')->count() === 0) {
            Schema::drop('bible_verses');
        }

        if (Schema::hasTable('bible_verses')) {
            return;
        }

        Schema::rename('verse', 'bible_verses');

        Schema::table('bible_verses', function (Blueprint $table): void {
            if (! Schema::hasColumn('bible_verses', 'bible_version_id')) {
                $table->unsignedBigInteger('bible_version_id')->nullable()->after('id');
                $table->index('bible_version_id');
            }

            if (! Schema::hasColumn('bible_verses', 'bible_book_id')) {
                $table->unsignedBigInteger('bible_book_id')->nullable()->after('bible_version_id');
                $table->index('bible_book_id');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function loadAbbreviationMap(): array
    {
        if (! Schema::hasTable('_legacy_book_abbreviation_map')) {
            return [];
        }

        $map = [];

        foreach (DB::table('_legacy_book_abbreviation_map')->get() as $row) {
            $map[mb_strtolower((string) $row->name)] = (string) $row->abbreviation;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $abbrevMap
     */
    private function resolveUsfm(string $abbreviation, string $name, array $abbrevMap): ?string
    {
        $candidate = strtoupper(trim($abbreviation));

        if ($candidate !== '' && BibleBookCatalog::hasBook($candidate)) {
            return $candidate;
        }

        $key = mb_strtolower(trim($name));

        if ($key === '') {
            return null;
        }

        return $abbrevMap[$key] ?? null;
    }
};
