<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The `(language, type_id, date)` UNIQUE on `devotionals` is sequenced
 * alongside MBA-027 which introduces the `type_id` column — adding it
 * here would be premature.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devotional_type') && ! Schema::hasTable('devotional_entry')) {
            return;
        }

        ReconcileTableHelper::rename('devotional_type', 'devotional_types');
        ReconcileTableHelper::rename('devotional_entry', 'devotionals');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('devotionals', 'devotional_entry');
        ReconcileTableHelper::rename('devotional_types', 'devotional_type');
    }
};
