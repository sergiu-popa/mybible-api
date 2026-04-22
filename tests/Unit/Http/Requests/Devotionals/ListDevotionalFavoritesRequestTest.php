<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Devotionals;

use App\Http\Requests\Devotionals\ListDevotionalFavoritesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ListDevotionalFavoritesRequestTest extends TestCase
{
    public function test_it_accepts_valid_per_page(): void
    {
        $this->assertTrue(Validator::make(['per_page' => 10], (new ListDevotionalFavoritesRequest)->rules())->passes());
    }

    public function test_it_rejects_per_page_over_max(): void
    {
        $validator = Validator::make(
            ['per_page' => ListDevotionalFavoritesRequest::MAX_PER_PAGE + 1],
            (new ListDevotionalFavoritesRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
    }
}
