<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Canonical reference string, e.g. "GEN.1:1.VDC". Indexed for reverse-lookup.
            $table->string('reference');
            // Denormalised 3-letter book abbreviation (e.g. "GEN"). Stored alongside
            // the canonical reference so ?book= filtering becomes an index hit rather
            // than a LIKE 'GEN.%' prefix scan. Derived at write time from the parsed
            // Reference; never edited directly.
            $table->string('book', 3);
            $table->text('content');
            $table->timestamps();

            $table->index('reference');
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'book']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
