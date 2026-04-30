<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\References;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ValidateReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_it_returns_parsed_payload_for_a_canonical_single_verse(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.references.validate'), [
            'reference' => 'GEN.1:1.VDC',
        ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('references.0.book', 'GEN')
            ->assertJsonPath('references.0.chapter', 1)
            ->assertJsonPath('references.0.verses.0', 1)
            ->assertJsonPath('references.0.version', 'VDC');
    }

    public function test_it_returns_422_for_an_unparseable_reference(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.references.validate'), [
            'reference' => 'NOT-A-REFERENCE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(route('admin.references.validate'), ['reference' => 'GEN.1.VDC'])
            ->assertUnauthorized();
    }

    public function test_it_blocks_non_admins(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.references.validate'), ['reference' => 'GEN.1.VDC'])
            ->assertForbidden();
    }

    public function test_it_validates_input_shape(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.references.validate'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }
}
