<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Bible;

use Database\Seeders\BibleCanonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListBibleBooksTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
        $this->seed(BibleCanonSeeder::class);
    }

    public function test_it_returns_all_sixty_six_books_in_canonical_order(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.index'));

        $response
            ->assertOk()
            ->assertJsonCount(66, 'data')
            ->assertJsonPath('data.0.abbreviation', 'GEN')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.65.abbreviation', 'REV')
            ->assertJsonPath('data.65.position', 66)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'abbreviation',
                    'name',
                    'testament',
                    'position',
                    'chapter_count',
                ]],
            ]);
    }

    public function test_it_defaults_name_to_english_when_no_language_is_specified(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Genesis');
    }

    public function test_it_honours_an_explicit_language(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Geneza');
    }

    public function test_it_does_not_paginate(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.index'))
            ->assertOk()
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('books.index'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_an_unsupported_language(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.index', ['language' => 'zz']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }
}
