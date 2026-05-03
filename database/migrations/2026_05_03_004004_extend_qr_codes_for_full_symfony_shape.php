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
        if (! Schema::hasTable('qr_codes')) {
            return;
        }

        Schema::table('qr_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('qr_codes', 'place')) {
                $table->string('place')->default('')->after('id');
            }
            if (! Schema::hasColumn('qr_codes', 'base_url')) {
                $table->string('base_url')->default('')->after('place');
            }
            if (! Schema::hasColumn('qr_codes', 'source')) {
                $table->string('source')->default('')->after('base_url');
            }
            if (! Schema::hasColumn('qr_codes', 'destination')) {
                $table->string('destination')->default('')->after('source');
            }
            if (! Schema::hasColumn('qr_codes', 'name')) {
                $table->string('name', 50)->default('')->after('destination');
            }
            if (! Schema::hasColumn('qr_codes', 'content')) {
                $table->longText('content')->nullable()->after('name');
            }
            if (! Schema::hasColumn('qr_codes', 'description')) {
                $table->longText('description')->nullable()->after('content');
            }
        });

        // Backfill from legacy `url` column for existing rows.
        if (Schema::hasColumn('qr_codes', 'url')) {
            DB::statement("UPDATE qr_codes SET destination = url WHERE destination = ''");
            DB::statement('UPDATE qr_codes SET content = url WHERE content IS NULL');
        }

        // Make reference nullable now that not all QR rows are verse-targeted.
        if (Schema::hasColumn('qr_codes', 'reference')) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->string('reference')->nullable()->change();
            });
        }

        // Make content NOT NULL only when all rows have non-NULL content.
        $contentNullCount = DB::table('qr_codes')->whereNull('content')->count();
        if ($contentNullCount === 0 && Schema::hasColumn('qr_codes', 'content')) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->longText('content')->nullable(false)->change();
            });
        }

        // UNIQUE (place, source) — only when no two rows would collide on the
        // current values.
        $duplicates = DB::table('qr_codes')
            ->select('place', 'source')
            ->groupBy('place', 'source')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $hasUnique = collect(Schema::getIndexes('qr_codes'))
            ->contains(fn (array $idx): bool => ($idx['unique'] ?? false)
                && $idx['columns'] === ['place', 'source'],
            );

        if ($duplicates === 0 && ! $hasUnique) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->unique(['place', 'source'], 'qr_codes_place_source_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('qr_codes')) {
            return;
        }

        Schema::table('qr_codes', function (Blueprint $table): void {
            $hasUnique = collect(Schema::getIndexes('qr_codes'))
                ->contains(fn (array $idx): bool => ($idx['unique'] ?? false)
                    && $idx['columns'] === ['place', 'source'],
                );

            if ($hasUnique) {
                $table->dropUnique('qr_codes_place_source_unique');
            }

            foreach (['description', 'content', 'name', 'destination', 'source', 'base_url', 'place'] as $col) {
                if (Schema::hasColumn('qr_codes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
