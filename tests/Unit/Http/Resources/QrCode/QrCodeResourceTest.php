<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\QrCode;

use App\Domain\QrCode\Models\QrCode;
use App\Http\Resources\QrCode\QrCodeResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QrCodeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_reference_url_and_image_url(): void
    {
        Storage::fake('qr');

        $qrCode = QrCode::factory()->create([
            'reference' => 'GEN.1:1.VDC',
            'url' => 'https://web.example/gen-1-1',
            'image_path' => 'gen-1-1.png',
        ]);

        $array = QrCodeResource::make($qrCode)->toArray(new Request);

        $this->assertSame('GEN.1:1.VDC', $array['reference']);
        $this->assertSame('https://web.example/gen-1-1', $array['url']);
        $this->assertIsString($array['image_url']);
        $this->assertStringContainsString('gen-1-1.png', $array['image_url']);
    }

    public function test_it_returns_null_image_url_when_image_path_is_null(): void
    {
        $qrCode = QrCode::factory()->withoutImage()->create([
            'reference' => 'GEN.1:1.VDC',
        ]);

        $array = QrCodeResource::make($qrCode)->toArray(new Request);

        $this->assertNull($array['image_url']);
    }
}
