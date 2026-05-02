<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collection')) {
            return;
        }

        ReconcileTableHelper::rename('collection', 'collections');
        ReconcileTableHelper::rename('collection_topic', 'collection_topics');
        ReconcileTableHelper::rename('collection_reference', 'collection_references');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('collection_references', 'collection_reference');
        ReconcileTableHelper::rename('collection_topics', 'collection_topic');
        ReconcileTableHelper::rename('collections', 'collection');
    }
};
