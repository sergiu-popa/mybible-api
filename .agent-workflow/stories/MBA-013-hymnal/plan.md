# Plan: MBA-013-hymnal

## Approach

Port the Symfony hymnal catalog + favorites into a new `App\Domain\Hymnal` domain, mirroring the `ReadingPlans` shape: Eloquent models backed by QueryBuilders, Actions for mutations, Form Requests and API Resources for the HTTP layer. Language-aware fields (`name`, `title`, `author`, `composer`, `copyright`, `stanzas`) are stored as translation maps and rendered via the existing `LanguageResolver` against the `ResolveRequestLanguage` middleware's attribute. Favorites toggle is a single idempotent endpoint that returns 201 on insert / 200 on delete, per AC 8.

## Open questions — resolutions

1. **Stanza structure.** Store a per-language `stanzas` column cast to `array` whose value is a list of `{ index: int, text: string, is_chorus: bool }` shapes. The migration normalises whatever Symfony shipped (JSON column, delimiter string, or sibling table) into this shape at seed time — Resource contract stays stable regardless of upstream storage. Chorus is a flag on a stanza rather than a sibling field so repeats (`C, 1, C, 2, C`) render in order.
2. **Audio files.** Out of scope for MBA-013 per story. No `audio_url` column, no S3 wiring. Deferred to a follow-up story.
3. **Book-level favorites.** Not in scope; Symfony only favorites songs. `hymnal_favorite` maps `(user_id, hymnal_song_id)` only.

## Domain layout

```
app/Domain/Hymnal/
├── Models/
│   ├── HymnalBook.php
│   ├── HymnalSong.php
│   └── HymnalFavorite.php
├── QueryBuilders/
│   ├── HymnalBookQueryBuilder.php
│   ├── HymnalSongQueryBuilder.php
│   └── HymnalFavoriteQueryBuilder.php
├── Actions/
│   └── ToggleHymnalFavoriteAction.php
├── DataTransferObjects/
│   └── ToggleHymnalFavoriteData.php
└── Exceptions/
    └── (none this story)

app/Http/Controllers/Api/V1/Hymnal/
├── ListHymnalBooksController.php
├── ListHymnalBookSongsController.php
├── ShowHymnalSongController.php
├── ListHymnalFavoritesController.php
└── ToggleHymnalFavoriteController.php

app/Http/Requests/Hymnal/
├── ListHymnalBooksRequest.php
├── ListHymnalBookSongsRequest.php
├── ShowHymnalSongRequest.php
├── ListHymnalFavoritesRequest.php
└── ToggleHymnalFavoriteRequest.php

app/Http/Resources/Hymnal/
├── HymnalBookResource.php
├── HymnalSongSummaryResource.php   # list rows (no lyrics)
├── HymnalSongResource.php          # detail with stanzas
└── HymnalFavoriteResource.php
```

## Key types

| Type | Role |
|---|---|
| `HymnalBook` | Eloquent model. Translatable `name` (array cast). Columns: `id`, `slug`, `name`, `language` (iso2 — primary locale of the book), `position`, timestamps, soft deletes. `songs(): HasMany` ordered by `number`. Uses `HymnalBookQueryBuilder`. Route key = `slug`. |
| `HymnalSong` | Eloquent model. Columns: `id`, `hymnal_book_id`, `number` (int, nullable), `title` (array), `author` (array, nullable), `composer` (array, nullable), `copyright` (array, nullable), `stanzas` (array — list of `{index, text, is_chorus}` per locale key), timestamps, soft deletes. `book(): BelongsTo`. Uses `HymnalSongQueryBuilder`. Route key = `id`. |
| `HymnalFavorite` | Eloquent model. Columns: `id`, `user_id`, `hymnal_song_id`, `created_at`. No `updated_at`. Unique index on `(user_id, hymnal_song_id)`. `song(): BelongsTo`. `user(): BelongsTo`. Uses `HymnalFavoriteQueryBuilder`. |
| `HymnalBookQueryBuilder` | `forLanguage(Language): self` filters books whose `language` matches; `withSongCount(): self` adds a `songs_count` aggregate via `withCount('songs')`. |
| `HymnalSongQueryBuilder` | `forBook(HymnalBook): self`; `search(string $query, Language $language): self` — `LIKE` on the locale key of `title` JSON; when `$query` is numeric, also OR-matches on `number`. |
| `HymnalFavoriteQueryBuilder` | `forUser(User): self`; `forSong(HymnalSong): self`; `withSong(): self` eager-loads `song.book`. |
| `ToggleHymnalFavoriteData` (readonly) | Fields: `User $user`, `HymnalSong $song`. Built in the controller from the authenticated user + the resolved song id. |
| `ToggleHymnalFavoriteAction` | `execute(ToggleHymnalFavoriteData): ToggleHymnalFavoriteResult` where `ToggleHymnalFavoriteResult` is a readonly struct `{ HymnalFavorite $favorite, bool $created }`. When `$created` is false, `$favorite` holds the just-deleted row (for Resource hydration); controller translates to 201 / 200 + `{ deleted: true }`. |
| `HymnalBookResource` | Shape: `{ id, slug, name, language, song_count }`. `name` resolved via `LanguageResolver` against `ResolveRequestLanguage` attribute. `song_count` read from the aggregate (null-safe when the QB method was not applied). |
| `HymnalSongSummaryResource` | List rows. Shape: `{ id, number, title, book: { id, slug } }`. |
| `HymnalSongResource` | Detail. Shape: `{ id, number, title, author, composer, copyright, stanzas: [{ index, text, is_chorus }], book: { id, slug, name } }`. All translatable fields resolved via `LanguageResolver`; `stanzas` picks the current language's array (empty list fallback). |
| `HymnalFavoriteResource` | Shape: `{ id, created_at, song: HymnalSongResource }`. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth / middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/hymnal-books` | `ListHymnalBooksController` | `ListHymnalBooksRequest` | `HymnalBookResource` (collection) | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/hymnal-books/{book:slug}/songs` | `ListHymnalBookSongsController` | `ListHymnalBookSongsRequest` | `HymnalSongSummaryResource` (collection) | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/hymnal-songs/{song}` | `ShowHymnalSongController` | `ShowHymnalSongRequest` | `HymnalSongResource` | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/hymnal-favorites` | `ListHymnalFavoritesController` | `ListHymnalFavoritesRequest` | `HymnalFavoriteResource` (collection) | `auth:sanctum` |
| POST | `/api/v1/hymnal-favorites/toggle` | `ToggleHymnalFavoriteController` | `ToggleHymnalFavoriteRequest` | `HymnalFavoriteResource` on create / JSON `{ deleted: true }` on delete | `auth:sanctum` |

- The three catalog endpoints also set `Cache-Control: public, max-age=3600` (AC 5). Apply via a `cache.headers:public;max_age=3600;etag` middleware on the catalog group (existing framework alias) rather than hand-rolling headers per controller.
- `ListHymnalBooksRequest` accepts `?language={iso2}` (string) and `?per_page` (1..100, default 15). The `language` filter constrains which books are returned; `ResolveRequestLanguage` still governs the translation locale for the `name` field — they are separate concerns.
- `ListHymnalBookSongsRequest` accepts `?search` (nullable string) and `?per_page` (1..200, default 50).
- `ShowHymnalSongRequest` / `ListHymnalFavoritesRequest` have `authorize() === true` and an empty rule set (Sanctum handles auth).
- `ToggleHymnalFavoriteRequest` validates `song_id` as `required|integer|exists:hymnal_songs,id`. `authorize()` delegates to the authenticated user being present (the uniqueness + ownership are enforced by the Action on `(user_id, song_id)` — no owner-authorize block needed, so this story does **not** contribute to the deferred-extractions tripwire count).

### Route-model binding notes

- `{book:slug}` binds to `HymnalBook` by its `slug` column. The model is **not** scoped (no `published()` equivalent; all rows are public catalog), so default binding is fine. If a future story introduces a `published_at` gate on books, switch to the same `resolveRouteBinding` override used by `ReadingPlan` (per guidelines §5b).
- `{song}` binds to `HymnalSong` by `id` (default). No scoping.
- `hymnal-favorites/toggle` does not bind a favorite; the Action looks up `(user_id, song_id)` itself.

## Data & migrations

Three migrations in one story (single feature set, atomic up/down):

- `create_hymnal_books_table` — `id`, `slug` (unique), `name` json, `language` char(2) indexed, `position` unsigned int default 0, timestamps, soft deletes.
- `create_hymnal_songs_table` — `id`, `hymnal_book_id` fk cascadeOnDelete, `number` unsigned int nullable (indexed with `hymnal_book_id` for `(book, number)` uniqueness when number is set — partial unique not portable, so enforce at the Action layer if needed; ship a non-unique composite index for now), `title` json, `author` json nullable, `composer` json nullable, `copyright` json nullable, `stanzas` json, timestamps, soft deletes.
- `create_hymnal_favorites_table` — `id`, `user_id` fk cascadeOnDelete, `hymnal_song_id` fk cascadeOnDelete, `created_at` nullable. Unique composite index on `(user_id, hymnal_song_id)`.

No `updated_at` on favorites; they are insert-or-delete, never updated.

### Factories & seed parity

- `HymnalBookFactory`, `HymnalSongFactory`, `HymnalFavoriteFactory` — each ships a default happy-path state mirroring the shapes in the QueryBuilder + Resource tests. State helpers: `HymnalBookFactory::forLanguage(Language)`, `HymnalSongFactory::withStanzas(int $count)`. No seeder this story — Symfony import is out of scope here.

## Tasks

- [ ] 1. Create migrations `create_hymnal_books_table`, `create_hymnal_songs_table`, `create_hymnal_favorites_table` with the columns, indexes, and cascade rules above. One migration file per table.
- [ ] 2. Create `App\Domain\Hymnal\Models\HymnalBook`, `HymnalSong`, `HymnalFavorite` with casts, relations, and `newEloquentBuilder()` wiring.
- [ ] 3. Create `HymnalBookQueryBuilder` with `forLanguage()` and `withSongCount()`.
- [ ] 4. Create `HymnalSongQueryBuilder` with `forBook()` and `search()` (numeric branch ORs on `number`).
- [ ] 5. Create `HymnalFavoriteQueryBuilder` with `forUser()`, `forSong()`, `withSong()`.
- [ ] 6. Create `HymnalBookFactory`, `HymnalSongFactory`, `HymnalFavoriteFactory` with the stated states.
- [ ] 7. Create readonly DTO `ToggleHymnalFavoriteData` and readonly result struct `ToggleHymnalFavoriteResult` under `App\Domain\Hymnal\DataTransferObjects`.
- [ ] 8. Create `ToggleHymnalFavoriteAction` that upserts/deletes the favorite inside a transaction and returns `ToggleHymnalFavoriteResult`.
- [ ] 9. Create `HymnalBookResource`, `HymnalSongSummaryResource`, `HymnalSongResource`, `HymnalFavoriteResource` using `LanguageResolver` + `ResolveRequestLanguage::ATTRIBUTE_KEY`.
- [ ] 10. Create `ListHymnalBooksRequest`, `ListHymnalBookSongsRequest`, `ShowHymnalSongRequest`, `ListHymnalFavoritesRequest`, `ToggleHymnalFavoriteRequest` with the rules listed in HTTP endpoints.
- [ ] 11. Create `ListHymnalBooksController` — delegates to `HymnalBook::query()->forLanguage()->withSongCount()->orderBy('position')->paginate($request->perPage())`.
- [ ] 12. Create `ListHymnalBookSongsController` — resolves `HymnalBook` via `{book:slug}`, queries `HymnalSong::query()->forBook($book)->search($request->search(), $language)->orderBy('number')->paginate($request->perPage())`.
- [ ] 13. Create `ShowHymnalSongController` — eager-loads `book`, returns `HymnalSongResource`.
- [ ] 14. Create `ListHymnalFavoritesController` — queries `HymnalFavorite::query()->forUser($user)->withSong()->orderByDesc('created_at')->paginate($request->perPage())`.
- [ ] 15. Create `ToggleHymnalFavoriteController` — builds DTO, invokes Action, returns `HymnalFavoriteResource` with status 201 when `$created === true`, else `response()->json(['deleted' => true], 200)`.
- [ ] 16. Register routes in `routes/api.php` under the `v1` prefix: catalog group with `['api-key-or-sanctum', 'resolve-language', 'cache.headers:public;max_age=3600;etag']`; favorites group with `auth:sanctum`.
- [ ] 17. Feature test `ListHymnalBooksTest` — language filter, pagination, `song_count` aggregate present, `Cache-Control` header, auth gate (both api-key and sanctum accepted).
- [ ] 18. Feature test `ListHymnalBookSongsTest` — book-scoped listing, numeric search branch, textual search branch, 404 on unknown slug, pagination caps at 200.
- [ ] 19. Feature test `ShowHymnalSongTest` — stanzas structure, language fallback, 404 on unknown id, `Cache-Control` header.
- [ ] 20. Feature test `ListHymnalFavoritesTest` — auth required (401 without token), own favorites only (cross-user rows invisible), embedded song payload.
- [ ] 21. Feature test `ToggleHymnalFavoriteTest` — first call returns 201, second call returns 200 with `{deleted: true}`, validation error on unknown `song_id`, cross-user independence (two users can favorite the same song without collision).
- [ ] 22. Unit test `ToggleHymnalFavoriteActionTest` — insert branch creates a row, delete branch removes it, transactional rollback on downstream failure.
- [ ] 23. Unit test `HymnalSongQueryBuilderTest` — `search()` numeric branch matches on `number`, textual branch matches on the current-language title key, non-matching language key is not returned.
- [ ] 24. Run `make lint-fix`, `make stan`, `make test filter=Hymnal`; finally `make test` before marking the story ready for review.

## Risks & notes

- **Symfony schema not introspectable.** The target database is empty locally (migrations not run), so the Symfony `hymn` / `hymnal` tables weren't inspected via `database-schema`. Column list is taken from the story's description (`id, bookId, number, title`) plus the speculative stanza/metadata fields. The Engineer should confirm the Symfony source shape during implementation and adjust the migration — without changing the Resource contract in this plan.
- **Stanzas cast per-language.** The `stanzas` JSON column stores `{ "<iso2>": [{ index, text, is_chorus }, ...] }`. `HymnalSongResource` resolves the current language key via `LanguageResolver`, then exposes the array as-is. If Symfony stored a flat delimited string, the import job (future story) normalises into this shape — the Resource stays stable.
- **Numeric search overload.** `search('23')` matches songs with `number = 23` OR whose localised title contains `"23"`. This matches Symfony behaviour per the story. A pure-number search that returns title-substring junk is a known quirk — accept it; stricter disambiguation (e.g. `?number=23` vs `?search=`) belongs in a later refinement story.
- **Route-model binding with `{book:slug}`.** Book has no scope (`published()` etc.), so default binding is used — no `resolveRouteBinding` override needed this story. Documented here so the Code Reviewer does not flag the absence.
- **Cache headers middleware.** Using Laravel's built-in `cache.headers` alias avoids hand-rolled per-controller header manipulation. Applied at the group level so adding new catalog endpoints inherits the policy automatically.
- **Deferred-extractions register.** This story adds zero new owner-`authorize()` blocks and zero new lifecycle `withProgressCounts()` consumers — tripwire counts are unaffected. `ToggleHymnalFavoriteRequest` uses the caller's authenticated identity directly rather than an owner-gate against a pre-resolved subscription, so it is not a copy of the 4x pattern tracked in §7.
- **Soft deletes on favorites — skip.** Favorites are toggled, not archived. `HymnalFavorite` has no `deleted_at` column; re-favoriting after deletion inserts a new row rather than restoring. Cleaner semantics, simpler unique-key story.
