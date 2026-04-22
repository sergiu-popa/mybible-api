<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class OlympiadThemeQuestionsResource extends ResourceCollection
{
    public $collects = OlympiadQuestionResource::class;

    public int $seed;

    /**
     * @param  iterable<int, OlympiadQuestion>  $resource
     */
    public function __construct(iterable $resource, int $seed)
    {
        parent::__construct($resource);

        $this->seed = $seed;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => ['seed' => $this->seed],
        ];
    }
}
