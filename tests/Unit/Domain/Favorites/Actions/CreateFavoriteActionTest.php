<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\CreateFavoriteAction;
use App\Domain\Favorites\DataTransferObjects\CreateFavoriteData;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Domain\Reference\Reference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_canonical_reference_form(): void
    {
        $user = User::factory()->create();

        $action = $this->app->make(CreateFavoriteAction::class);

        $favorite = $action->execute(new CreateFavoriteData(
            user: $user,
            reference: new Reference('GEN', 1, [1, 2, 3], 'VDC'),
            category: null,
            note: 'note',
            color: null,
        ));

        $this->assertSame('GEN.1:1-3.VDC', $favorite->reference);
        $this->assertSame($user->id, $favorite->user_id);
        $this->assertNull($favorite->category_id);
        $this->assertSame('note', $favorite->note);
    }

    public function test_it_assigns_a_category(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();

        $action = $this->app->make(CreateFavoriteAction::class);

        $favorite = $action->execute(new CreateFavoriteData(
            user: $user,
            reference: new Reference('JHN', 3, [16], 'VDC'),
            category: $category,
            note: null,
            color: null,
        ));

        $this->assertSame($category->id, $favorite->category_id);
        $this->assertSame('JHN.3:16.VDC', $favorite->reference);
    }

    public function test_it_persists_whole_chapter_reference(): void
    {
        $user = User::factory()->create();

        $action = $this->app->make(CreateFavoriteAction::class);

        $favorite = $action->execute(new CreateFavoriteData(
            user: $user,
            reference: new Reference('PSA', 23, [], 'VDC'),
            category: null,
            note: null,
            color: null,
        ));

        $this->assertSame('PSA.23.VDC', $favorite->reference);
    }
}
