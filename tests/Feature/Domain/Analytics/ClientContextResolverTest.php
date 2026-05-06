<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Analytics;

use App\Domain\Analytics\Enums\EventSource;
use App\Domain\Analytics\Support\ClientContextResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ClientContextResolverTest extends TestCase
{
    public function test_extracts_device_id_from_header(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('X-Device-Id', 'abc-123');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame('abc-123', $context->deviceId);
    }

    public function test_falls_back_to_body_device_id_when_header_missing(): void
    {
        $request = Request::create('/', 'POST', ['device_id' => 'body-id']);

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame('body-id', $context->deviceId);
    }

    public function test_returns_null_device_id_when_neither_provided(): void
    {
        $request = Request::create('/', 'POST');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertNull($context->deviceId);
    }

    public function test_extracts_language_from_request_attribute(): void
    {
        $request = Request::create('/', 'POST');
        $request->attributes->set(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::Ro);

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame('ro', $context->language);
    }

    public function test_infers_ios_source_from_user_agent(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('User-Agent', 'MyBibleMobile/1.0 ios');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame(EventSource::Ios, $context->source);
    }

    public function test_infers_android_source_from_user_agent(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('User-Agent', 'MyBibleMobile/1.0 android');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame(EventSource::Android, $context->source);
    }

    public function test_infers_web_source_from_browser_user_agent(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Chrome/100');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertSame(EventSource::Web, $context->source);
    }

    public function test_returns_null_source_for_unrecognised_user_agent(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('User-Agent', 'curl/8.0');

        $context = ClientContextResolver::fromRequest($request);

        $this->assertNull($context->source);
    }

    public function test_truncates_overlong_device_id_to_64_chars(): void
    {
        $long = str_repeat('a', 100);
        $request = Request::create('/', 'POST');
        $request->headers->set('X-Device-Id', $long);

        $context = ClientContextResolver::fromRequest($request);

        $this->assertNotNull($context->deviceId);
        $this->assertSame(64, mb_strlen($context->deviceId ?? ''));
    }
}
