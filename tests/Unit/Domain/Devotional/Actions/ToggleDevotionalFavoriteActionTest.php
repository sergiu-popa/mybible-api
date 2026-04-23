<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Devotional\Actions;

use App\Domain\Devotional\Actions\ToggleDevotionalFavoriteAction;
use App\Domain\Devotional\DataTransferObjects\ToggleDevotionalFavoriteData;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToggleDevotionalFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_favorite_when_none_exists(): void
    {
        $user = User::factory()->create();
        $devotional = Devotional::factory()->create();

        $result = (new ToggleDevotionalFavoriteAction)
            ->execute(new ToggleDevotionalFavoriteData($user, $devotional->id));

        $this->assertTrue($result->created);
        $this->assertNotNull($result->favorite);
        $this->assertSame($user->id, $result->favorite->user_id);
        $this->assertSame($devotional->id, $result->favorite->devotional_id);
        $this->assertSame(1, DevotionalFavorite::query()->count());
    }

    public function test_it_removes_an_existing_favorite(): void
    {
        $user = User::factory()->create();
        $devotional = Devotional::factory()->create();
        DevotionalFavorite::factory()->forUser($user)->create(['devotional_id' => $devotional->id]);

        $result = (new ToggleDevotionalFavoriteAction)
            ->execute(new ToggleDevotionalFavoriteData($user, $devotional->id));

        $this->assertFalse($result->created);
        $this->assertNull($result->favorite);
        $this->assertSame(0, DevotionalFavorite::query()->count());
    }

    public function test_it_scopes_toggles_to_the_calling_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $devotional = Devotional::factory()->create();

        DevotionalFavorite::factory()->forUser($other)->create(['devotional_id' => $devotional->id]);

        $result = (new ToggleDevotionalFavoriteAction)
            ->execute(new ToggleDevotionalFavoriteData($user, $devotional->id));

        $this->assertTrue($result->created);
        $this->assertSame(2, DevotionalFavorite::query()->count());
        $this->assertTrue(
            DevotionalFavorite::query()->where('user_id', $other->id)->exists(),
            'Other user favorite should be untouched.',
        );
    }

    public function test_created_result_loads_devotional_relation(): void
    {
        $user = User::factory()->create();
        $devotional = Devotional::factory()->create();

        $result = (new ToggleDevotionalFavoriteAction)
            ->execute(new ToggleDevotionalFavoriteData($user, $devotional->id));

        $this->assertNotNull($result->favorite);
        $this->assertTrue($result->favorite->relationLoaded('devotional'));
        $this->assertSame($devotional->id, $result->favorite->devotional->id);
    }
}
