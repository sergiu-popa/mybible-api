<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\DataTransferObjects;

use App\Domain\Auth\DataTransferObjects\LoginUserData;
use PHPUnit\Framework\TestCase;

final class LoginUserDataTest extends TestCase
{
    public function test_it_builds_a_dto_from_a_validated_payload(): void
    {
        $data = LoginUserData::from([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->assertSame('jane@example.com', $data->email);
        $this->assertSame('secret-pass', $data->password);
    }
}
