<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Http\Resources\Olympiad\OlympiadThemeResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class OlympiadThemeResourceTest extends TestCase
{
    public function test_maps_projection_row_into_theme_shape(): void
    {
        // Simulate the aggregate row returned by
        // `OlympiadQuestionQueryBuilder::themes()`.
        $row = new OlympiadQuestion;
        $row->setRawAttributes([
            'book' => 'GEN',
            'chapters_from' => 1,
            'chapters_to' => 3,
            'language' => 'en',
            'question_count' => 7,
        ], true);

        $payload = (new OlympiadThemeResource($row))->toArray(Request::create('/'));

        $this->assertSame([
            'id' => 'GEN.1-3.en',
            'book' => 'GEN',
            'chapters_from' => 1,
            'chapters_to' => 3,
            'language' => 'en',
            'question_count' => 7,
        ], $payload);
    }
}
