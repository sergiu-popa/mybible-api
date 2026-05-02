<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('resource_book')
            && ! Schema::hasTable('resource_book_chapter')
            && ! Schema::hasTable('resource_download')
        ) {
            return;
        }

        ReconcileTableHelper::rename('resource_book', 'resource_books');
        ReconcileTableHelper::rename('resource_book_chapter', 'resource_book_chapters');
        ReconcileTableHelper::rename('resource_download', 'resource_downloads');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('resource_downloads', 'resource_download');
        ReconcileTableHelper::rename('resource_book_chapters', 'resource_book_chapter');
        ReconcileTableHelper::rename('resource_books', 'resource_book');
    }
};
