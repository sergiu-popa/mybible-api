<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `reading_progress` (Symfony) is superseded by reading plans — confirmed
 * product decision. Drop is gated on emptiness so the migration aborts
 * loudly if any row is still present, rather than silently destroying
 * data. Reversible by recreating the original shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reading_progress')) {
            return;
        }

        $rows = DB::table('reading_progress')->count();

        if ($rows > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to drop reading_progress: %d row(s) still present. Verify supersession before dropping.',
                $rows,
            ));
        }

        Schema::drop('reading_progress');
    }

    public function down(): void
    {
        if (Schema::hasTable('reading_progress')) {
            return;
        }

        Schema::create('reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('book', 8);
            $table->unsignedSmallInteger('chapter');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
};
