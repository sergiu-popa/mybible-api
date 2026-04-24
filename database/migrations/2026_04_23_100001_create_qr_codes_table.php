<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qr_codes')) {
            return;
        }

        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->string('url');
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique('reference', 'qr_codes_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
