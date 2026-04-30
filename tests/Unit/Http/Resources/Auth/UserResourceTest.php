<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Auth;

use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_expected_shape(): void
    {
        $user = User::factory()->create();

        $array = UserResource::make($user)->resolve();

        $this->assertSame(
            [
                'id',
                'name',
                'email',
                'language',
                'languages',
                'ui_locale',
                'preferred_version',
                'avatar_url',
                'is_super',
                'active',
                'created_at',
            ],
            array_keys($array),
        );

        $this->assertSame($user->id, $array['id']);
        $this->assertSame($user->name, $array['name']);
        $this->assertSame($user->email, $array['email']);
        $this->assertSame($user->language, $array['language']);
        $this->assertSame($user->languages, $array['languages']);
        $this->assertSame($user->ui_locale, $array['ui_locale']);
        $this->assertSame($user->preferred_version, $array['preferred_version']);
        $this->assertNull($array['avatar_url']);
        $this->assertFalse($array['is_super']);
        $this->assertTrue($array['active']);
    }

    public function test_it_does_not_leak_sensitive_fields(): void
    {
        $user = User::factory()->create();

        $array = UserResource::make($user)->resolve();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('email_verified_at', $array);
    }
}
