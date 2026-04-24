<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\DataTransferObjects;

use App\Domain\Shared\Enums\Language;
use App\Domain\User\Profile\DataTransferObjects\UpdateUserProfileData;
use PHPUnit\Framework\TestCase;

final class UpdateUserProfileDataTest extends TestCase
{
    public function test_from_reads_every_field_and_casts_language_to_enum(): void
    {
        $data = UpdateUserProfileData::from([
            'name' => 'Jane',
            'language' => 'ro',
            'preferred_version' => 'VDC',
        ]);

        $this->assertSame('Jane', $data->name);
        $this->assertSame(Language::Ro, $data->language);
        $this->assertSame('VDC', $data->preferredVersion);
    }

    public function test_from_defaults_missing_keys_to_null(): void
    {
        $data = UpdateUserProfileData::from([]);

        $this->assertNull($data->name);
        $this->assertNull($data->language);
        $this->assertNull($data->preferredVersion);
    }
}
