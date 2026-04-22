<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_verse')) {
            return;
        }

        Schema::create('daily_verse', function (Blueprint $table): void {
            $table->id();
            $table->date('for_date')->unique();
            $table->string('reference', 25);
            $table->text('image_cdn_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_verse');
    }
};
