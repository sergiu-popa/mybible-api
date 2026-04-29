<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ShowSabbathSchoolLessonAction;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\SabbathSchool\ShowSabbathSchoolLessonRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ShowSabbathSchoolLessonController
{
    private const CACHE_MAX_AGE = 3600;

    /**
     * Show a Sabbath School lesson.
     *
     * Returns the lesson detail including all segments and their questions.
     * Implicit route-model binding is intentionally bypassed — the published
     * + detail eager-load runs *inside* the Action's cache rebuild closure
     * so cache hits skip the bind query entirely. 404s for unpublished /
     * missing lessons still flow through the JSON exception handler via the
     * `ModelNotFoundException` raised by `findOrFail()`.
     */
    public function __invoke(
        ShowSabbathSchoolLessonRequest $request,
        ShowSabbathSchoolLessonAction $action,
    ): JsonResponse {
        $resolved = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $resolved instanceof Language ? $resolved : Language::En;

        $lessonId = (int) $request->route('lesson');

        $payload = $action->execute($lessonId, $language);

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
