<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Long-running admin imports (Bible catalog, commentary, etc.) need a
     * uniform progress tracker so the admin can poll a single endpoint
     * regardless of which capability owns the import. Each row tracks one
     * import attempt; queued jobs update `status` / `progress` / `error`
     * as they run so the admin polls a stable shape.
     */
    public function up(): void
    {
        if (Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            // `users.id` is an unsigned `int` (legacy Symfony shape), not a
            // bigint, so define the FK column at the same width to keep the
            // engine from rejecting the constraint with error 3780.
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id', 'import_jobs_user_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status'], 'import_jobs_type_status_idx');
            $table->index(['user_id', 'created_at'], 'import_jobs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
