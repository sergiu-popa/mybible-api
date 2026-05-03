<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empty archive mirror of the pre-reshape highlights schema.
 *
 * MBA-031 ETL writes here when a legacy `passage` string can't be
 * cleanly mapped onto the new (segment_content_id, start, end) shape.
 * Read-only after population — no FK to live tables so an archived
 * highlight survives subsequent segment/lesson deletions for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_highlights_legacy')) {
            return;
        }

        Schema::create('sabbath_school_highlights_legacy', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('sabbath_school_segment_id');
            $table->string('passage', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->index('user_id', 'sabbath_school_highlights_legacy_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_highlights_legacy');
    }
};
