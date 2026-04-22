<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\Models;

use App\Domain\Bible\Models\BibleVersion;
use Tests\TestCase;

final class BibleVersionTest extends TestCase
{
    public function test_route_key_name_is_abbreviation(): void
    {
        $this->assertSame('abbreviation', (new BibleVersion)->getRouteKeyName());
    }
}
