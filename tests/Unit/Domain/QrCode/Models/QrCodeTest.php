<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrCode\Models;

use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\QueryBuilders\QrCodeQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_custom_query_builder(): void
    {
        $this->assertInstanceOf(QrCodeQueryBuilder::class, QrCode::query());
    }

    public function test_image_url_returns_a_url_resolved_from_the_qr_disk(): void
    {
        Storage::fake('qr');

        $qrCode = QrCode::factory()->create([
            'reference' => 'GEN.1:1.VDC',
            'image_path' => 'GEN-1-1-VDC.png',
        ]);

        $url = $qrCode->imageUrl();

        $this->assertIsString($url);
        $this->assertStringContainsString('GEN-1-1-VDC.png', $url);
    }

    public function test_image_url_returns_null_when_image_path_is_null(): void
    {
        $qrCode = QrCode::factory()->withoutImage()->create();

        $this->assertNull($qrCode->imageUrl());
    }
}
