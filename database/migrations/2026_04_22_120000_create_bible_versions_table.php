<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bible_versions')) {
            return;
        }

        Schema::create('bible_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('abbreviation')->unique();
            $table->string('language', 8)->index();
            $table->timestamps();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_versions');
    }
};
