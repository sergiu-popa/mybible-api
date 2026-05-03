<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\Models\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ResolveDevotionalTypeAction
{
    /**
     * Resolve a `type` query-string value (legacy enum or admin-defined slug)
     * to a `DevotionalType` model. Language-specific rows win over global ones.
     *
     * @throws ModelNotFoundException
     */
    public function handle(string $typeParam, Language $language): DevotionalType
    {
        $slug = mb_strtolower(trim($typeParam));

        if ($slug === '') {
            throw (new ModelNotFoundException)->setModel(DevotionalType::class);
        }

        $type = DevotionalType::query()
            ->forSlugAndLanguage($slug, $language)
            ->first();

        if ($type === null) {
            throw (new ModelNotFoundException)->setModel(DevotionalType::class, [$slug]);
        }

        return $type;
    }
}
