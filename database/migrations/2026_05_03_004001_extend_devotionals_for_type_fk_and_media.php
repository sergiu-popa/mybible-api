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
        if (! Schema::hasTable('devotionals')) {
            return;
        }

        Schema::table('devotionals', function (Blueprint $table): void {
            if (! Schema::hasColumn('devotionals', 'type_id')) {
                $table->foreignId('type_id')
                    ->nullable()
                    ->after('language')
                    ->constrained('devotional_types')
                    ->restrictOnDelete();
            }
            if (! Schema::hasColumn('devotionals', 'audio_cdn_url')) {
                $table->text('audio_cdn_url')->nullable()->after('content');
            }
            if (! Schema::hasColumn('devotionals', 'audio_embed')) {
                $table->longText('audio_embed')->nullable()->after('audio_cdn_url');
            }
            if (! Schema::hasColumn('devotionals', 'video_embed')) {
                $table->longText('video_embed')->nullable()->after('audio_embed');
            }
        });

        // Backfill: link devotionals to their type via the existing string slug.
        if (Schema::hasColumn('devotionals', 'type')) {
            DB::statement(
                'UPDATE devotionals d INNER JOIN devotional_types t ON t.slug = d.type AND t.language IS NULL '
                . 'SET d.type_id = t.id WHERE d.type_id IS NULL',
            );
        }

        // Make NOT NULL only when every row was successfully matched.
        $orphaned = DB::table('devotionals')->whereNull('type_id')->count();

        if ($orphaned === 0) {
            Schema::table('devotionals', function (Blueprint $table): void {
                $table->foreignId('type_id')->nullable(false)->change();
            });
        }

        $hasUnique = collect(Schema::getIndexes('devotionals'))
            ->contains(fn (array $idx): bool => ($idx['unique'] ?? false)
                && $idx['columns'] === ['language', 'type_id', 'date'],
            );

        if (! $hasUnique && $orphaned === 0) {
            Schema::table('devotionals', function (Blueprint $table): void {
                $table->unique(['language', 'type_id', 'date'], 'devotionals_language_type_id_date_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('devotionals')) {
            return;
        }

        Schema::table('devotionals', function (Blueprint $table): void {
            $hasUnique = collect(Schema::getIndexes('devotionals'))
                ->contains(fn (array $idx): bool => ($idx['unique'] ?? false)
                    && $idx['columns'] === ['language', 'type_id', 'date'],
                );

            if ($hasUnique) {
                $table->dropUnique('devotionals_language_type_id_date_unique');
            }

            if (Schema::hasColumn('devotionals', 'type_id')) {
                $table->dropConstrainedForeignId('type_id');
            }

            foreach (['video_embed', 'audio_embed', 'audio_cdn_url'] as $col) {
                if (Schema::hasColumn('devotionals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
