<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('devotionals')) {
            return;
        }

        Schema::create('devotionals', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->char('language', 2);
            $table->string('type', 16);
            $table->string('title');
            $table->longText('content');
            $table->string('passage')->nullable();
            $table->string('author')->nullable();
            $table->timestamps();

            $table->index(['language', 'type', 'date'], 'devotionals_language_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotionals');
    }
};
