<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brings the `commentaries` table to the Laravel-final shape. In a fresh
 * environment the create migration already laid down every column this
 * migration touches, so each branch is a no-op. In production, the
 * Symfony reconcile rename brought the legacy shape (`name VARCHAR`,
 * `language VARCHAR(3)`, no `slug`/`is_published`/`source_commentary_id`)
 * — this migration evolves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commentaries')) {
            return;
        }

        $this->ensureLanguageBackfilled();
        $this->ensureLanguageCharTwo();
        $this->ensureNameJson();
        $this->ensureSourceCommentaryColumn();
        $this->ensurePublishedColumn();
        $this->ensureSlugColumn();
        $this->ensureLanguagePublishedIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('commentaries')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            if (Schema::hasColumn('commentaries', 'source_commentary_id')) {
                $table->dropForeign(['source_commentary_id']);
                $table->dropColumn('source_commentary_id');
            }

            if (Schema::hasColumn('commentaries', 'is_published')) {
                $table->dropColumn('is_published');
            }

            if (Schema::hasColumn('commentaries', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }

    private function ensureLanguageBackfilled(): void
    {
        if (! Schema::hasColumn('commentaries', 'language')) {
            return;
        }

        (new BackfillLegacyLanguageCodesAction)->handle('commentaries', 'language');
    }

    private function ensureLanguageCharTwo(): void
    {
        if (! Schema::hasColumn('commentaries', 'language')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->char('language', 2)->change();
        });
    }

    /**
     * Symfony stored a single VARCHAR `name`. Convert to JSON keyed by
     * the row's `language` so resources can resolve via LanguageResolver.
     * Skip if the values already parse as JSON (i.e. a previous run or
     * fresh-create laid them down already).
     */
    private function ensureNameJson(): void
    {
        if (! Schema::hasColumn('commentaries', 'name')) {
            return;
        }

        $rows = DB::table('commentaries')->select(['id', 'name', 'language'])->get();

        foreach ($rows as $row) {
            $value = (string) $row->name;

            if ($value === '') {
                continue;
            }

            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                continue;
            }

            $language = is_string($row->language) && $row->language !== ''
                ? $row->language
                : 'ro';

            DB::table('commentaries')
                ->where('id', $row->id)
                ->update(['name' => json_encode([$language => $value], JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function ensureSourceCommentaryColumn(): void
    {
        if (Schema::hasColumn('commentaries', 'source_commentary_id')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->foreignId('source_commentary_id')
                ->nullable()
                ->after('language')
                ->constrained('commentaries')
                ->nullOnDelete();
        });
    }

    private function ensurePublishedColumn(): void
    {
        if (Schema::hasColumn('commentaries', 'is_published')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->boolean('is_published')->default(false)->after('language');
        });
    }

    private function ensureSlugColumn(): void
    {
        if (Schema::hasColumn('commentaries', 'slug')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('id');
        });

        $taken = [];

        DB::table('commentaries')
            ->select(['id', 'abbreviation', 'language'])
            ->orderBy('id')
            ->each(function ($row) use (&$taken): void {
                $base = Str::slug(mb_strtolower((string) $row->abbreviation));

                if ($base === '') {
                    $base = 'commentary-' . $row->id;
                }

                $candidate = $base;

                if (isset($taken[$candidate])) {
                    $candidate = $base . '-' . (string) $row->language;
                }

                $suffix = 2;
                while (isset($taken[$candidate])) {
                    $candidate = $base . '-' . (string) $row->language . '-' . $suffix;
                    $suffix++;
                }

                $taken[$candidate] = true;

                DB::table('commentaries')
                    ->where('id', $row->id)
                    ->update(['slug' => $candidate]);
            });

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    private function ensureLanguagePublishedIndex(): void
    {
        if (! Schema::hasColumn('commentaries', 'language') || ! Schema::hasColumn('commentaries', 'is_published')) {
            return;
        }

        if ($this->hasIndex('commentaries_language_published_idx')) {
            return;
        }

        Schema::table('commentaries', function (Blueprint $table): void {
            $table->index(['language', 'is_published'], 'commentaries_language_published_idx');
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('commentaries') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
