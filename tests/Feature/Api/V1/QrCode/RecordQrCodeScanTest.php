<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\QrCode;

use App\Domain\QrCode\Events\QrCodeScanned;
use App\Domain\QrCode\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class RecordQrCodeScanTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_records_a_scan_and_dispatches_event(): void
    {
        Event::fake([QrCodeScanned::class]);

        $qr = QrCode::factory()->create([
            'place' => 'cafe-A',
            'source' => 'campaign-X',
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('qr-codes.scans.store', ['qr' => $qr->id]))
            ->assertNoContent();

        Event::assertDispatched(
            QrCodeScanned::class,
            fn (QrCodeScanned $e): bool => $e->qrCodeId === $qr->id
                && $e->place === 'cafe-A'
                && $e->source === 'campaign-X',
        );
    }

    public function test_it_returns_404_for_unknown_qr(): void
    {
        Event::fake([QrCodeScanned::class]);

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('qr-codes.scans.store', ['qr' => 999_999]))
            ->assertNotFound();

        Event::assertNotDispatched(QrCodeScanned::class);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $qr = QrCode::factory()->create();

        $this->postJson(route('qr-codes.scans.store', ['qr' => $qr->id]))
            ->assertUnauthorized();
    }
}
