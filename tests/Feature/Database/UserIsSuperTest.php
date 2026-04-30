<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserIsSuperTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_is_super_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'is_super'));
    }

    public function test_is_super_defaults_to_false(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->is_super);
    }

    public function test_super_factory_state_marks_user_as_super_admin(): void
    {
        $user = User::factory()->super()->create();

        $this->assertTrue($user->is_super);
        $this->assertContains('admin', $user->roles);
    }

    public function test_admin_factory_state_does_not_imply_super(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertFalse($user->is_super);
        $this->assertContains('admin', $user->roles);
    }

    public function test_is_super_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_super' => 1]);

        $this->assertSame(true, $user->refresh()->is_super);
    }
}
