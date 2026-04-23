<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Http\Resources\Olympiad\OlympiadQuestionResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class OlympiadQuestionResourceTest extends TestCase
{
    public function test_exposes_question_and_answers_with_is_correct(): void
    {
        $correct = new OlympiadAnswer;
        $correct->setRawAttributes([
            'id' => 1,
            'text' => 'Right',
            'is_correct' => true,
            'position' => 0,
        ], true);

        $wrong = new OlympiadAnswer;
        $wrong->setRawAttributes([
            'id' => 2,
            'text' => 'Wrong',
            'is_correct' => false,
            'position' => 1,
        ], true);

        $question = new OlympiadQuestion;
        $question->setRawAttributes([
            'id' => 42,
            'book' => 'GEN',
            'chapters_from' => 1,
            'chapters_to' => 1,
            'language' => 'en',
            'question' => 'Who?',
            'explanation' => 'Because.',
        ], true);
        $question->setRelation('answers', new Collection([$correct, $wrong]));

        $payload = (new OlympiadQuestionResource($question))->toArray(Request::create('/'));

        $this->assertSame(42, $payload['id']);
        $this->assertSame('Who?', $payload['question']);
        $this->assertSame('Because.', $payload['explanation']);
        $this->assertCount(2, $payload['answers']);
        $this->assertTrue($payload['answers'][0]->toArray(Request::create('/'))['is_correct']);
    }
}
