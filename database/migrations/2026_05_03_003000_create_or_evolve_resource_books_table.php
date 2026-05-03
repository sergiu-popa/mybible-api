<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brings `resource_books` to the Laravel-final shape. In a fresh
 * environment the table does not exist; in production the MBA-023
 * reconcile rename brought the legacy `resource_book` shape (Symfony
 * `name`, `language` VARCHAR(3), `description`) — this migration creates
 * the missing table or adds the missing columns idempotently and
 * backfills `slug`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_books')) {
            Schema::create('resource_books', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 255)->unique();
                $table->string('name', 255);
                $table->char('language', 2);
                $table->longText('description')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->string('cover_image_url', 255)->nullable();
                $table->string('author', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['language', 'is_published', 'position'],
                    'resource_books_language_published_position_idx',
                );
            });

            return;
        }

        $this->ensureLanguageBackfilled();
        $this->ensureLanguageCharTwo();
        $this->ensureSimpleColumns();
        $this->ensureSlugColumn();
        $this->ensureLanguagePositionIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('resource_books')) {
            return;
        }

        Schema::table('resource_books', function (Blueprint $table): void {
            if ($this->hasIndex('resource_books_language_published_position_idx')) {
                $table->dropIndex('resource_books_language_published_position_idx');
            }

            foreach (['author', 'cover_image_url', 'published_at', 'is_published', 'position', 'deleted_at'] as $column) {
                if (Schema::hasColumn('resource_books', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('resource_books', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }

    private function ensureLanguageBackfilled(): void
    {
        if (! Schema::hasColumn('resource_books', 'language')) {
            return;
        }

        (new BackfillLegacyLanguageCodesAction)->handle('resource_books', 'language');
    }

    private function ensureLanguageCharTwo(): void
    {
        if (! Schema::hasColumn('resource_books', 'language')) {
            return;
        }

        Schema::table('resource_books', function (Blueprint $table): void {
            $table->char('language', 2)->change();
        });
    }

    private function ensureSimpleColumns(): void
    {
        Schema::table('resource_books', function (Blueprint $table): void {
            if (! Schema::hasColumn('resource_books', 'position')) {
                $table->unsignedInteger('position')->default(0)->after('language');
            }

            if (! Schema::hasColumn('resource_books', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('position');
            }

            if (! Schema::hasColumn('resource_books', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_published');
            }

            if (! Schema::hasColumn('resource_books', 'cover_image_url')) {
                $table->string('cover_image_url', 255)->nullable()->after('published_at');
            }

            if (! Schema::hasColumn('resource_books', 'author')) {
                $table->string('author', 255)->nullable()->after('cover_image_url');
            }

            if (! Schema::hasColumn('resource_books', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    private function ensureSlugColumn(): void
    {
        if (Schema::hasColumn('resource_books', 'slug')) {
            return;
        }

        Schema::table('resource_books', function (Blueprint $table): void {
            $table->string('slug', 255)->nullable()->after('id');
        });

        $taken = [];

        DB::table('resource_books')
            ->select(['id', 'name', 'language'])
            ->orderBy('id')
            ->each(function ($row) use (&$taken): void {
                $base = Str::slug(mb_strtolower((string) $row->name));

                if ($base === '') {
                    $base = 'resource-book-' . $row->id;
                }

                $candidate = $base;
                $suffix = 2;
                while (isset($taken[$candidate])) {
                    $candidate = $base . '-' . $suffix;
                    $suffix++;
                }

                $taken[$candidate] = true;

                DB::table('resource_books')
                    ->where('id', $row->id)
                    ->update(['slug' => $candidate]);
            });

        Schema::table('resource_books', function (Blueprint $table): void {
            $table->string('slug', 255)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    private function ensureLanguagePositionIndex(): void
    {
        if ($this->hasIndex('resource_books_language_published_position_idx')) {
            return;
        }

        Schema::table('resource_books', function (Blueprint $table): void {
            $table->index(
                ['language', 'is_published', 'position'],
                'resource_books_language_published_position_idx',
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
