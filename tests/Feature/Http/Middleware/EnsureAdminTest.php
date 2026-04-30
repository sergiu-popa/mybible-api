<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class EnsureAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'admin'])
            ->get('/_test/admin-only', fn (Request $request) => response()->json([
                'user_id' => $request->user()?->id,
            ]));
    }

    public function test_it_rejects_unauthenticated_requests_with_401(): void
    {
        $this->getJson('/_test/admin-only')->assertUnauthorized();
    }

    public function test_it_rejects_authenticated_non_admin_users_with_403(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-only')
            ->assertForbidden();
    }

    public function test_it_allows_users_with_the_admin_role(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-only')
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_it_allows_super_admins(): void
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/admin-only')
            ->assertOk();
    }
}
