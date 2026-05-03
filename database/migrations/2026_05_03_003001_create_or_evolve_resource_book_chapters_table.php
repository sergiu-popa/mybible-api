<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `resource_book_chapters` to the Laravel-final shape. In a fresh
 * environment the table does not exist; in production the MBA-023 rename
 * brought the legacy `resource_book_chapter` shape with `resource_book`
 * (FK column) and possibly narrow audio columns — this migration creates
 * the table or evolves the existing one idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_book_chapters')) {
            Schema::create('resource_book_chapters', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('resource_book_id')
                    ->constrained('resource_books')
                    ->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('title', 255);
                $table->longText('content');
                $table->text('audio_cdn_url')->nullable();
                $table->text('audio_embed')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamps();

                $table->unique(['resource_book_id', 'position'], 'resource_book_chapters_book_position_unique');
            });

            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('resource_book_chapters', 'resource_book', 'resource_book_id');

        $this->ensureForeignKey();
        $this->widenAudioColumns();
        $this->ensureDurationColumn();
        $this->ensureUniquePosition();
    }

    public function down(): void
    {
        if (! Schema::hasTable('resource_book_chapters')) {
            return;
        }

        Schema::table('resource_book_chapters', function (Blueprint $table): void {
            if ($this->hasIndex('resource_book_chapters_book_position_unique')) {
                $table->dropUnique('resource_book_chapters_book_position_unique');
            }

            if (Schema::hasColumn('resource_book_chapters', 'duration_seconds')) {
                $table->dropColumn('duration_seconds');
            }
        });
    }

    private function ensureForeignKey(): void
    {
        if (! Schema::hasColumn('resource_book_chapters', 'resource_book_id')) {
            return;
        }

        foreach (Schema::getForeignKeys('resource_book_chapters') as $foreign) {
            if (in_array('resource_book_id', $foreign['columns'] ?? [], true)) {
                return;
            }
        }

        Schema::table('resource_book_chapters', function (Blueprint $table): void {
            $table->foreign('resource_book_id')
                ->references('id')
                ->on('resource_books')
                ->cascadeOnDelete();
        });
    }

    private function widenAudioColumns(): void
    {
        Schema::table('resource_book_chapters', function (Blueprint $table): void {
            if (Schema::hasColumn('resource_book_chapters', 'audio_cdn_url')) {
                $table->text('audio_cdn_url')->nullable()->change();
            }

            if (Schema::hasColumn('resource_book_chapters', 'audio_embed')) {
                $table->text('audio_embed')->nullable()->change();
            }
        });
    }

    private function ensureDurationColumn(): void
    {
        if (Schema::hasColumn('resource_book_chapters', 'duration_seconds')) {
            return;
        }

        Schema::table('resource_book_chapters', function (Blueprint $table): void {
            $table->unsignedInteger('duration_seconds')->nullable();
        });
    }

    private function ensureUniquePosition(): void
    {
        if ($this->hasIndex('resource_book_chapters_book_position_unique')) {
            return;
        }

        Schema::table('resource_book_chapters', function (Blueprint $table): void {
            $table->unique(
                ['resource_book_id', 'position'],
                'resource_book_chapters_book_position_unique',
            );
        });
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
