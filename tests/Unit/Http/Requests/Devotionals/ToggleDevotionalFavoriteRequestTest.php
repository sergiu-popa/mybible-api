<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Devotionals;

use App\Http\Requests\Devotionals\ToggleDevotionalFavoriteRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ToggleDevotionalFavoriteRequestTest extends TestCase
{
    public function test_it_requires_devotional_id(): void
    {
        $validator = Validator::make([], (new ToggleDevotionalFavoriteRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('devotional_id', $validator->errors()->toArray());
    }

    public function test_it_rejects_non_integer_devotional_id(): void
    {
        $validator = Validator::make(
            ['devotional_id' => 'not-an-int'],
            (new ToggleDevotionalFavoriteRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
    }
}
