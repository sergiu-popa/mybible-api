<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserLanguageScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_languages_in_their_scope(): void
    {
        $user = User::factory()->admin()->withLanguages(['ro', 'hu'])->create();

        $this->assertTrue($user->canManageLanguage('ro'));
        $this->assertTrue($user->canManageLanguage('hu'));
    }

    public function test_admin_cannot_manage_languages_outside_their_scope(): void
    {
        $user = User::factory()->admin()->withLanguages(['ro'])->create();

        $this->assertFalse($user->canManageLanguage('hu'));
        $this->assertFalse($user->canManageLanguage('en'));
    }

    public function test_super_admin_can_manage_every_language(): void
    {
        $user = User::factory()->super()->withLanguages([])->create();

        $this->assertTrue($user->canManageLanguage('en'));
        $this->assertTrue($user->canManageLanguage('ro'));
        $this->assertTrue($user->canManageLanguage('hu'));
        $this->assertTrue($user->canManageLanguage('xx'));
    }

    public function test_only_super_admin_can_manage_languageless_rows(): void
    {
        $regular = User::factory()->admin()->withLanguages(['ro', 'hu', 'en'])->create();
        $super = User::factory()->super()->create();

        $this->assertFalse($regular->canManageLanguageless());
        $this->assertTrue($super->canManageLanguageless());
    }
}
