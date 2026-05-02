<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commentary') && ! Schema::hasTable('commentary_text')) {
            return;
        }

        ReconcileTableHelper::rename('commentary', 'commentaries');
        ReconcileTableHelper::rename('commentary_text', 'commentary_texts');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('commentary_texts', 'commentary_text');
        ReconcileTableHelper::rename('commentaries', 'commentary');
    }
};
