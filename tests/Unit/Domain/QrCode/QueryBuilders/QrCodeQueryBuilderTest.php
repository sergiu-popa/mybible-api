<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrCode\QueryBuilders;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QrCodeQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_reference_returns_the_matching_row(): void
    {
        $target = QrCode::factory()->forReference('GEN.1:1.VDC')->create();
        QrCode::factory()->forReference('EXO.2:3.VDC')->create();

        $found = QrCode::query()->forReference('GEN.1:1.VDC')->first();

        $this->assertNotNull($found);
        $this->assertSame($target->id, $found->id);
    }

    public function test_for_reference_returns_null_on_miss(): void
    {
        QrCode::factory()->forReference('GEN.1:1.VDC')->create();

        $this->assertNull(QrCode::query()->forReference('JHN.3:16.VDC')->first());
    }
}
