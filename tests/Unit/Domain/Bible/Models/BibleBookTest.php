<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\Models;

use App\Domain\Bible\Models\BibleBook;
use Tests\TestCase;

final class BibleBookTest extends TestCase
{
    public function test_route_key_name_is_abbreviation(): void
    {
        $this->assertSame('abbreviation', (new BibleBook)->getRouteKeyName());
    }
}
