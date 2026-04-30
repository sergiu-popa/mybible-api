<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Users;

use App\Domain\Auth\Notifications\PasswordResetNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $super;
    }

    public function test_list_returns_admins_only(): void
    {
        $this->actingAsSuper();

        User::factory()->admin()->create(['name' => 'Bob']);
        User::factory()->admin()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Carol Civilian']);

        $response = $this->getJson(route('admin.users.index'))
            ->assertOk();

        /** @var list<array{name: string}> $rows */
        $rows = $response->json('data');
        $names = array_column($rows, 'name');

        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Carol Civilian', $names);
    }

    public function test_list_is_blocked_for_non_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_create_provisions_an_admin_with_random_password(): void
    {
        $this->actingAsSuper();

        $response = $this->postJson(route('admin.users.store'), [
            'name' => 'New Editor',
            'email' => 'new.editor@example.test',
            'languages' => ['ro', 'hu'],
            'ui_locale' => 'ro',
            'is_super' => false,
        ])->assertCreated();

        $response
            ->assertJsonPath('data.email', 'new.editor@example.test')
            ->assertJsonPath('data.languages', ['ro', 'hu'])
            ->assertJsonPath('data.ui_locale', 'ro')
            ->assertJsonPath('data.is_super', false)
            ->assertJsonPath('data.active', true);

        $created = User::where('email', 'new.editor@example.test')->firstOrFail();
        $this->assertContains('admin', $created->roles);
        $this->assertNotEmpty($created->password);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.users.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $this->actingAsSuper();

        User::factory()->admin()->create(['email' => 'taken@example.test']);

        $this->postJson(route('admin.users.store'), [
            'name' => 'Dup',
            'email' => 'taken@example.test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_disable_flips_is_active_and_revokes_tokens(): void
    {
        $this->actingAsSuper();

        $target = User::factory()->admin()->create();
        $target->createToken('mobile');
        $target->createToken('desktop');

        $this->assertSame(2, $target->tokens()->count());

        $this->patchJson(route('admin.users.disable', ['user' => $target->id]))
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertSame(0, $target->refresh()->tokens()->count());
    }

    public function test_enable_flips_is_active_back(): void
    {
        $this->actingAsSuper();

        $target = User::factory()->admin()->inactive()->create();

        $this->patchJson(route('admin.users.enable', ['user' => $target->id]))
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }

    public function test_password_reset_sends_reset_link(): void
    {
        Notification::fake();

        $this->actingAsSuper();

        $target = User::factory()->admin()->create(['email' => 'editor@example.test']);

        $this->postJson(route('admin.users.password-reset', ['user' => $target->id]))
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($target, PasswordResetNotification::class);
    }
}
