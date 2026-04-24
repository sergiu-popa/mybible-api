<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_highlights')) {
            return;
        }

        Schema::create('sabbath_school_highlights', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('sabbath_school_segment_id')
                ->constrained('sabbath_school_segments')
                ->cascadeOnDelete();
            // Canonical reference string, e.g. "GEN.1:1.VDC".
            $table->string('passage');
            $table->timestamps();

            $table->index(['user_id', 'sabbath_school_segment_id'], 'sabbath_school_highlights_user_segment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_highlights');
    }
};
