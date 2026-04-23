<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Http\Resources\Devotionals\DevotionalFavoriteResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class DevotionalFavoriteResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_the_devotional_when_loaded(): void
    {
        $user = User::factory()->create();
        $devotional = Devotional::factory()->create(['title' => 'Embedded']);
        $favorite = DevotionalFavorite::factory()
            ->forUser($user)
            ->create(['devotional_id' => $devotional->id])
            ->load('devotional');

        $resolved = DevotionalFavoriteResource::make($favorite)->response(new Request)->getData(true);
        $array = $resolved['data'];

        $this->assertSame($favorite->id, $array['id']);
        $this->assertIsString($array['created_at']);
        $this->assertIsArray($array['devotional']);
        $this->assertSame('Embedded', $array['devotional']['title']);
    }
}
