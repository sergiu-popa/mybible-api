<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Olympiad;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReorderOlympiadQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_it_persists_full_ordering_inside_a_theme(): void
    {
        $this->actingAsAdmin();

        $a = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $b = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $c = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();

        $this->postJson(route('admin.olympiad.questions.reorder'), [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);
    }

    public function test_it_ignores_questions_from_other_themes(): void
    {
        $this->actingAsAdmin();

        $anchor = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $sibling = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create();
        $foreigner = OlympiadQuestion::factory()->forTheme('EXO', 1, 3, Language::En)->create([
            'position' => 99,
        ]);

        $this->postJson(route('admin.olympiad.questions.reorder'), [
            'ids' => [$anchor->id, $sibling->id, $foreigner->id],
        ])->assertOk();

        $this->assertSame(1, $anchor->refresh()->position);
        $this->assertSame(2, $sibling->refresh()->position);
        $this->assertSame(99, $foreigner->refresh()->position);
    }

    public function test_it_blocks_non_admin_users(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.olympiad.questions.reorder'), ['ids' => [1]])
            ->assertForbidden();
    }
}
