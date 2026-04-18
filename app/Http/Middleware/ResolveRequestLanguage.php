<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Enums\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveRequestLanguage
{
    public const ATTRIBUTE_KEY = 'language';

    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->query('language');
        $value = is_string($raw) ? $raw : null;

        $request->attributes->set(self::ATTRIBUTE_KEY, Language::fromRequest($value));

        return $next($request);
    }
}
