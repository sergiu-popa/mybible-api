<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devotional_types')) {
            Schema::create('devotional_types', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 64);
                $table->string('title', 128);
                $table->unsignedSmallInteger('position')->default(0);
                $table->char('language', 2)->nullable();
                $table->timestamps();

                $table->unique(['slug', 'language'], 'devotional_types_slug_language_unique');
            });
        } else {
            Schema::table('devotional_types', function (Blueprint $table): void {
                if (! Schema::hasColumn('devotional_types', 'slug')) {
                    $table->string('slug', 64)->after('id');
                }
                if (! Schema::hasColumn('devotional_types', 'title')) {
                    $table->string('title', 128)->after('slug');
                }
                if (! Schema::hasColumn('devotional_types', 'position')) {
                    $table->unsignedSmallInteger('position')->default(0)->after('title');
                }
                if (! Schema::hasColumn('devotional_types', 'language')) {
                    $table->char('language', 2)->nullable()->after('position');
                }
                if (! Schema::hasColumn('devotional_types', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('devotional_types', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $indexes = collect(Schema::getIndexes('devotional_types'));

            $hasSluLangUnique = $indexes->contains(
                fn (array $idx): bool => ($idx['unique'] ?? false) && $idx['columns'] === ['slug', 'language'],
            );
            $hasSlugOnlyUnique = $indexes->contains(
                fn (array $idx): bool => ($idx['unique'] ?? false) && $idx['columns'] === ['slug'],
            );

            if ($hasSlugOnlyUnique && ! $hasSluLangUnique) {
                Schema::table('devotional_types', function (Blueprint $table): void {
                    $table->dropUnique(['slug']);
                });
            }

            if (! $hasSluLangUnique) {
                Schema::table('devotional_types', function (Blueprint $table): void {
                    $table->unique(['slug', 'language'], 'devotional_types_slug_language_unique');
                });
            }
        }

        $now = now();

        foreach ([
            ['slug' => 'adults', 'title' => 'Adults', 'position' => 1],
            ['slug' => 'kids', 'title' => 'Kids', 'position' => 2],
        ] as $row) {
            $exists = DB::table('devotional_types')->where('slug', $row['slug'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('devotional_types')->insert([
                'slug' => $row['slug'],
                'title' => $row['title'],
                'position' => $row['position'],
                'language' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: this migration is part of the broader devotional_types
        // evolution (MBA-027). Reversing would orphan FKs in `devotionals`.
    }
};
