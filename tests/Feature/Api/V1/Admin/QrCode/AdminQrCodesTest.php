<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\QrCode;

use App\Domain\QrCode\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminQrCodesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_blocked_for_non_super(): void
    {
        $this->actingAsAdmin();
        $this->getJson(route('admin.qr-codes.index'))->assertForbidden();
    }

    public function test_create_persists_full_symfony_shape(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.qr-codes.store'), [
            'place' => 'cafe-A',
            'base_url' => 'https://qr.mybible.local',
            'source' => 'campaign-X',
            'destination' => 'https://web.mybible.local/destination',
            'name' => 'Cafe A QR',
            'content' => 'GEN.1:1.VDC',
            'description' => 'A QR for cafe A',
            'reference' => 'GEN.1:1.VDC',
        ])
            ->assertCreated()
            ->assertJsonPath('data.place', 'cafe-A')
            ->assertJsonPath('data.source', 'campaign-X')
            ->assertJsonPath('data.destination', 'https://web.mybible.local/destination')
            ->assertJsonPath('data.content', 'GEN.1:1.VDC');

        $this->assertDatabaseHas('qr_codes', [
            'place' => 'cafe-A',
            'source' => 'campaign-X',
            'name' => 'Cafe A QR',
        ]);
    }

    public function test_create_unique_place_source_collision_returns_422(): void
    {
        $this->actingAsSuper();

        QrCode::factory()->create(['place' => 'cafe-A', 'source' => 'campaign-X']);

        $this->postJson(route('admin.qr-codes.store'), [
            'place' => 'cafe-A',
            'base_url' => 'https://qr.mybible.local',
            'source' => 'campaign-X',
            'destination' => 'https://web.mybible.local/2',
            'name' => 'Dup',
            'content' => 'x',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);
    }

    public function test_update_changes_fields(): void
    {
        $this->actingAsSuper();

        $qr = QrCode::factory()->create();

        $this->patchJson(route('admin.qr-codes.update', ['qr' => $qr->id]), [
            'name' => 'Renamed',
            'description' => 'New desc',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.description', 'New desc');
    }

    public function test_destroy_removes_row(): void
    {
        $this->actingAsSuper();

        $qr = QrCode::factory()->create();

        $this->deleteJson(route('admin.qr-codes.destroy', ['qr' => $qr->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('qr_codes', ['id' => $qr->id]);
    }
}
