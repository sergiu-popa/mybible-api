<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Mobile;

use App\Http\Resources\Mobile\MobileVersionResource;
use Illuminate\Http\Request;
use Tests\TestCase;

final class MobileVersionResourceTest extends TestCase
{
    public function test_it_emits_the_locked_mobile_contract_field_names(): void
    {
        $resource = MobileVersionResource::make([
            'platform' => 'ios',
            'minimum_supported_version' => '3.2.0',
            'latest_version' => '3.4.1',
            'update_url' => 'https://apps.apple.com/app/id1',
            'force_update_below' => '3.0.0',
        ]);

        $array = $resource->toArray(new Request);

        $this->assertSame([
            'platform' => 'ios',
            'minimum_supported_version' => '3.2.0',
            'latest_version' => '3.4.1',
            'update_url' => 'https://apps.apple.com/app/id1',
            'force_update_below' => '3.0.0',
        ], $array);
    }

    public function test_it_defaults_missing_values_to_null(): void
    {
        $resource = MobileVersionResource::make([
            'platform' => 'android',
        ]);

        $array = $resource->toArray(new Request);

        $this->assertSame('android', $array['platform']);
        $this->assertNull($array['minimum_supported_version']);
        $this->assertNull($array['latest_version']);
        $this->assertNull($array['update_url']);
        $this->assertNull($array['force_update_below']);
    }
}
