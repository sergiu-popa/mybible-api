<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Verses;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Verses\ResolveVersesRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ResolveVersesRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BibleVersion::factory()->create(['abbreviation' => 'VDC', 'language' => 'ro']);
        BibleVersion::factory()->create(['abbreviation' => 'KJV', 'language' => 'en']);
    }

    public function test_explicit_version_query_param_wins(): void
    {
        $data = $this->buildRequest(['version' => 'kjv', 'book' => 'GEN', 'chapter' => 1, 'verses' => '1'])
            ->toData(new ReferenceParser);

        $this->assertSame('KJV', $data->version);
    }

    public function test_embedded_reference_version_beats_user_and_config(): void
    {
        $user = User::factory()->make();
        $user->setAttribute('preferred_version', 'KJV');

        $data = $this->buildRequest(['reference' => 'GEN.1:1.VDC'], user: $user)
            ->toData(new ReferenceParser);

        $this->assertSame('VDC', $data->version);
    }

    public function test_user_preferred_version_beats_config_default(): void
    {
        config()->set('bible.default_version_by_language', ['en' => 'KJV']);

        $user = User::factory()->make();
        $user->setAttribute('preferred_version', 'VDC');

        $data = $this->buildRequest(['book' => 'GEN', 'chapter' => 1, 'verses' => '1'], user: $user)
            ->toData(new ReferenceParser);

        $this->assertSame('VDC', $data->version);
    }

    public function test_language_config_default_is_last_tier_before_failure(): void
    {
        config()->set('bible.default_version_by_language', ['ro' => 'VDC']);

        $data = $this->buildRequest(
            ['book' => 'GEN', 'chapter' => 1, 'verses' => '1'],
            language: Language::Ro,
        )->toData(new ReferenceParser);

        $this->assertSame('VDC', $data->version);
    }

    public function test_it_throws_when_no_version_can_be_resolved(): void
    {
        config()->set('bible.default_version_by_language', []);

        $this->expectException(ValidationException::class);

        $this->buildRequest(['book' => 'GEN', 'chapter' => 1, 'verses' => '1'])
            ->toData(new ReferenceParser);
    }

    public function test_it_throws_when_explicit_version_is_unknown(): void
    {
        $this->expectException(ValidationException::class);

        $this->buildRequest(['version' => 'NOPE', 'book' => 'GEN', 'chapter' => 1, 'verses' => '1'])
            ->toData(new ReferenceParser);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildRequest(
        array $query,
        ?Authenticatable $user = null,
        Language $language = Language::En,
    ): ResolveVersesRequest {
        $request = ResolveVersesRequest::create('/api/v1/verses', 'GET', $query);
        $request->attributes->set(ResolveRequestLanguage::ATTRIBUTE_KEY, $language);

        if ($user !== null) {
            $request->setUserResolver(fn (): Authenticatable => $user);
        }

        return $request;
    }
}
