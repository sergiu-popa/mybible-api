<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `sabbath_school_trimesters` to the Laravel-final shape. In a
 * fresh environment the table does not exist; in production the
 * MBA-023 reconcile rename brought the legacy `sb_trimester` shape
 * (Symfony column names) — this migration creates the missing table
 * or adds any missing columns and the unique index idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_trimesters')) {
            Schema::create('sabbath_school_trimesters', function (Blueprint $table): void {
                $table->id();
                $table->string('year', 4);
                $table->char('language', 2);
                $table->string('age_group', 50);
                $table->string('title', 128);
                $table->smallInteger('number');
                $table->date('date_from');
                $table->date('date_to');
                $table->text('image_cdn_url')->nullable();
                $table->timestamps();

                $table->unique(
                    ['language', 'age_group', 'date_from', 'date_to'],
                    'sabbath_school_trimesters_lang_age_window_unique',
                );
            });

            return;
        }

        Schema::table('sabbath_school_trimesters', function (Blueprint $table): void {
            if (! Schema::hasColumn('sabbath_school_trimesters', 'year')) {
                $table->string('year', 4);
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'language')) {
                $table->char('language', 2);
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'age_group')) {
                $table->string('age_group', 50);
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'title')) {
                $table->string('title', 128);
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'number')) {
                $table->smallInteger('number');
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'date_from')) {
                $table->date('date_from');
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'date_to')) {
                $table->date('date_to');
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'image_cdn_url')) {
                $table->text('image_cdn_url')->nullable();
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('sabbath_school_trimesters', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        if (! $this->hasIndex('sabbath_school_trimesters_lang_age_window_unique')) {
            Schema::table('sabbath_school_trimesters', function (Blueprint $table): void {
                $table->unique(
                    ['language', 'age_group', 'date_from', 'date_to'],
                    'sabbath_school_trimesters_lang_age_window_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_trimesters');
    }

    private function hasIndex(string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND index_name = ? LIMIT 1',
            [$database, $name],
        ) !== null;
    }
};
