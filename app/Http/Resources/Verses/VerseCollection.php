<?php

declare(strict_types=1);

namespace App\Http\Resources\Verses;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class VerseCollection extends ResourceCollection
{
    public $collects = VerseResource::class;
}
