<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a JSON `languages` column to `users` storing the per-admin
     * content-language scope as a list of 2-char codes.
     *
     * Replaces the legacy single `language` column for scoping purposes —
     * the singular column is left in place to keep existing API consumers
     * working until they migrate. Both columns coexist for the duration of
     * the cutover; the singular one will be dropped in a later cleanup pass.
     */
    private const LEGACY_TO_TWO_CHAR = [
        'rom' => 'ro',
        'hun' => 'hu',
        'spa' => 'es',
        'fra' => 'fr',
        'ger' => 'de',
        'eng' => 'en',
        'ro' => 'ro',
        'hu' => 'hu',
        'es' => 'es',
        'fr' => 'fr',
        'de' => 'de',
        'en' => 'en',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'languages')) {
                $table->json('languages')->nullable()->after('language');
            }
        });

        DB::table('users')
            ->select(['id', 'language'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $code = is_string($row->language) ? strtolower($row->language) : null;
                    $mapped = $code !== null ? (self::LEGACY_TO_TWO_CHAR[$code] ?? null) : null;
                    $languages = $mapped !== null ? [$mapped] : [];

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['languages' => json_encode($languages)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'languages')) {
                $table->dropColumn('languages');
            }
        });
    }
};
