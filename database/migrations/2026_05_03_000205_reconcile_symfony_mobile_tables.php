<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_version')) {
            return;
        }

        ReconcileTableHelper::rename('mobile_version', 'mobile_versions');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('mobile_versions', 'mobile_version');
    }
};
