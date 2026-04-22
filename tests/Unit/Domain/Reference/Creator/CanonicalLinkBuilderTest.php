<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Creator;

use App\Domain\Reference\Creator\CanonicalLinkBuilder;
use PHPUnit\Framework\TestCase;

final class CanonicalLinkBuilderTest extends TestCase
{
    private CanonicalLinkBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CanonicalLinkBuilder;
    }

    public function test_emits_vdc_for_romanian(): void
    {
        $this->assertSame('GEN.17:2.VDC', $this->builder->build('GEN', '17:2', 'ro'));
    }

    public function test_emits_kar_for_hungarian(): void
    {
        $this->assertSame('1CO.1:1.KAR', $this->builder->build('1CO', '1:1', 'hu'));
    }

    public function test_emits_kjv_for_english(): void
    {
        $this->assertSame('GEN.17:2.KJV', $this->builder->build('GEN', '17:2', 'en'));
    }

    public function test_passes_through_composite_passage(): void
    {
        $this->assertSame('DEU.4:13;6:1-6.VDC', $this->builder->build('DEU', '4:13;6:1-6', 'ro'));
    }
}
