# Plan: MBA-015-olympiad

## Approach

Introduce an `App\Domain\Olympiad\*` domain backed by two new tables (`olympiad_questions`, `olympiad_answers`) keyed by book abbreviation + chapter range + language. Two invokable controllers: `ListOlympiadThemesController` (aggregates distinct `(book, chapters_from, chapters_to, language)` tuples with `question_count`) and `ShowOlympiadThemeController` (returns seeded-random questions + answers for a `{book}/{chapters}` path). Seeded randomization is done in-Action (`mt_srand → shuffle`), with the effective seed echoed back in the resource meta so clients can replay. Chapter-range parsing on the path segment reuses MBA-006's `ChapterRangeParser` semantics via a narrow new helper (see open question #4).

## Open questions — resolutions

1. **Answer model shape.** Child `olympiad_answers` table (belongs-to question, `is_correct boolean`, `position int`). A JSON column loses referential structure, makes admin tooling awkward, and complicates seeded per-answer shuffling. Cost is one extra migration — trivial.
2. **`is_correct` visibility.** Keep Symfony behaviour: expose `is_correct` on each answer. A server-side submit flow is out-of-scope and noted as a follow-up story hook.
3. **Language fallback.** Strict `404` when `(book, chapters, language)` has no rows — matches story AC 5 and avoids silent locale drift.
4. **Chapter-range parser reuse.** `ChapterRangeParser::expand()` operates on the canonical `BOOK.range.VERSION` triple and raises `InvalidReferenceException` for anything else. For the olympiad path segment we only need start/end integers, so introduce a small `ChapterRange` readonly VO plus `ChapterRange::fromSegment(string): self` in `App\Domain\Reference` that accepts `"5"` or `"1-3"`. This keeps a single source of truth for range syntax without overloading the existing parser's error surface. The VO is consumed by both olympiad controllers (theme listing to normalise query filters, theme show to bind the path segment).
5. **Theme identity.** No standalone `olympiad_themes` table — a "theme" is the distinct tuple projected from `olympiad_questions`. The listing endpoint's `id` is a deterministic composite string (`{BOOK}.{from}-{to}.{lang}`) so clients can store it opaquely; the show endpoint uses path params, not this id.
6. **Seed source.** If the request omits `seed`, the Action generates `random_int(1, PHP_INT_MAX)` and returns it in the resource collection's meta envelope. Clients re-send the same seed to replay ordering.

## Domain layout

```
app/Domain/Olympiad/
├── Models/
│   ├── OlympiadQuestion.php
│   └── OlympiadAnswer.php
├── QueryBuilders/
│   └── OlympiadQuestionQueryBuilder.php
├── DataTransferObjects/
│   ├── OlympiadThemeFilter.php
│   └── OlympiadThemeRequest.php
├── Actions/
│   ├── ListOlympiadThemesAction.php
│   └── FetchOlympiadThemeQuestionsAction.php
├── Support/
│   └── SeededShuffler.php
└── Exceptions/
    └── OlympiadThemeNotFoundException.php

app/Domain/Reference/
└── ChapterRange.php                     # new — shared with MBA-006
```

## Key types

| Type | Role |
|---|---|
| `OlympiadQuestion` (Eloquent) | `id`, `book` (string), `chapters_from` (int), `chapters_to` (int), `language` (`Language` enum cast), `question` (string), `explanation` (nullable string). `answers(): HasMany<OlympiadAnswer>`. `newEloquentBuilder()` returns `OlympiadQuestionQueryBuilder`. |
| `OlympiadAnswer` (Eloquent) | `id`, `olympiad_question_id`, `text` (string), `is_correct` (bool), `position` (int). |
| `OlympiadQuestionQueryBuilder` | `forLanguage(Language)`, `forBook(string)`, `forChapterRange(ChapterRange)`, `themes()` — returns the distinct-tuple + `COUNT(*)` projection for listing. |
| `ChapterRange` (readonly VO, `App\Domain\Reference`) | `int $from`, `int $to`. `fromSegment(string): self` (accepts `"5"` or `"1-3"`, throws `InvalidReferenceException::unparseable` on malformed input). `isSingleChapter(): bool`. `toCanonicalSegment(): string`. |
| `OlympiadThemeFilter` (readonly DTO) | `Language $language`, `int $page`, `int $perPage`. Built from `ListOlympiadThemesRequest`. |
| `OlympiadThemeRequest` (readonly DTO) | `string $book`, `ChapterRange $range`, `Language $language`, `?int $seed`. Built from `ShowOlympiadThemeRequest`. |
| `ListOlympiadThemesAction` | `execute(OlympiadThemeFilter): LengthAwarePaginator<ThemeRow>` where `ThemeRow` is `array{id:string,book:string,chapters_from:int,chapters_to:int,language:string,question_count:int}`. Delegates to `OlympiadQuestionQueryBuilder::themes()->paginate(...)`. |
| `FetchOlympiadThemeQuestionsAction` | `execute(OlympiadThemeRequest): OlympiadThemeResult` — loads questions + answers, applies `SeededShuffler` to both questions and each question's answers, returns `OlympiadThemeResult { Collection<OlympiadQuestion> $questions, int $seed }`. Throws `OlympiadThemeNotFoundException` when empty. |
| `OlympiadThemeResult` (readonly DTO) | `Collection<OlympiadQuestion> $questions`, `int $seed`. |
| `SeededShuffler` | `shuffle(array $items, int $seed): array`. Wraps `mt_srand` / `shuffle` so the seed state is set per-call and deterministic. Isolated so the Unit test can pin the algorithm (AC 8). |
| `OlympiadThemeNotFoundException` (extends `\RuntimeException`) | Rendered as `404` in `bootstrap/app.php`. Message: `Olympiad theme not found.`. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/olympiad/themes` | `ListOlympiadThemesController` | `ListOlympiadThemesRequest` | `OlympiadThemeResource::collection` | `api-key-or-sanctum` |
| GET | `/api/v1/olympiad/themes/{book}/{chapters}` | `ShowOlympiadThemeController` | `ShowOlympiadThemeRequest` | `OlympiadThemeQuestionsResource` (wraps the question collection + `seed` in `meta`) | `api-key-or-sanctum` |

Both routes sit under a `resolve-language` middleware group for language fallback parity with `reading-plans`. `{book}` is a plain string segment (not route-model bound — there is no `Book` model yet). `{chapters}` is parsed via `ChapterRange::fromSegment()` inside `ShowOlympiadThemeRequest::prepareForValidation()` / a typed accessor; malformed segments return `422` via `InvalidReferenceException` mapped in `bootstrap/app.php` (new handler).

Both responses set `Cache-Control: public, max-age=3600` via a response header applied in the controller (single-line header on the returned resource/response). No middleware abstraction — only two endpoints need this, so an abstraction would be premature.

## Resources

| Resource | Shape |
|---|---|
| `OlympiadThemeResource` | `{ id, book, chapters_from, chapters_to, language, question_count }` — consumes the paginator row (projection from `themes()`). |
| `OlympiadQuestionResource` | `{ id, question, explanation, answers: OlympiadAnswerResource[] }`. |
| `OlympiadAnswerResource` | `{ id, text, is_correct }`. |
| `OlympiadThemeQuestionsResource` (`ResourceCollection`) | Overrides `toArray` / `with` to emit `{ data: OlympiadQuestionResource[], meta: { seed } }`. |

## Data & migrations

One migration creating both tables:

- `olympiad_questions`: `id`, `book` (string, indexed), `chapters_from` (unsignedInteger), `chapters_to` (unsignedInteger), `language` (string, indexed), `question` (text), `explanation` (text, nullable), timestamps. Composite index on `(language, book, chapters_from, chapters_to)` to drive the themes query + show lookup.
- `olympiad_answers`: `id`, `olympiad_question_id` (foreignId, cascade on delete), `text` (text), `is_correct` (boolean), `position` (unsignedSmallInteger), timestamps. Index `(olympiad_question_id, position)`.

No seed data. Admin authoring is out of scope (story §Out of Scope).

## Tasks

- [ ] 1. Create the `olympiad_questions` + `olympiad_answers` migration (single file) with columns, composite indexes, and FK cascade as described. Run `make migrate` locally to validate the schema.
- [ ] 2. Create `App\Domain\Olympiad\Models\OlympiadQuestion` + `OlympiadAnswer` Eloquent models with casts (`language` → `Language` enum, `is_correct` → `bool`), `$guarded = []`, PHPDoc property blocks, and the `answers()` HasMany relation. Wire `newEloquentBuilder` on `OlympiadQuestion`.
- [ ] 3. Create `App\Domain\Olympiad\QueryBuilders\OlympiadQuestionQueryBuilder` with `forLanguage()`, `forBook()`, `forChapterRange()`, and `themes()`. Unit test with `RefreshDatabase`: themes groups distinct tuples + counts; range filter matches `chapters_from = from AND chapters_to = to` exactly.
- [ ] 4. Create readonly `App\Domain\Reference\ChapterRange` VO with `fromSegment()`, `isSingleChapter()`, `toCanonicalSegment()`. Unit test: `"5"` → `5..5`; `"1-3"` → `1..3`; `"0"`, `"3-1"`, `""`, `"a-b"`, `"1-"` all throw `InvalidReferenceException::unparseable` with a descriptive reason.
- [ ] 5. Create factories `OlympiadQuestionFactory` + `OlympiadAnswerFactory` (answers always bound to a question; states for `correct()` / `incorrect()`). Register via `#[UseFactory]`.
- [ ] 6. Create `App\Domain\Olympiad\Support\SeededShuffler::shuffle()`. Unit test: identical seed → identical order over a fixed input array across two calls; different seeds → different orders (odds of collision negligible for a 10-element array).
- [ ] 7. Create `App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter` + `OlympiadThemeRequest` readonly DTOs. No logic — field VOs only.
- [ ] 8. Create `App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException` and register its `404` renderer in `bootstrap/app.php`. Also register an `InvalidReferenceException` → `422` renderer (message + reason in payload).
- [ ] 9. Create `App\Domain\Olympiad\Actions\ListOlympiadThemesAction::execute()`. Unit test with `RefreshDatabase`: mixed-language + mixed-book fixture yields correct tuples, counts, language filter respected, pagination honoured.
- [ ] 10. Create `App\Domain\Olympiad\Actions\FetchOlympiadThemeQuestionsAction::execute()` returning `OlympiadThemeResult`. Unit test with `RefreshDatabase`: empty theme → throws `OlympiadThemeNotFoundException`; same seed → identical question + answer ordering across two calls; different seed → different question order (fixture large enough to make collision negligible); seed omitted → Action populates one and returns it in the result.
- [ ] 11. Create `App\Http\Requests\Olympiad\ListOlympiadThemesRequest` with `language` + `per_page` rules mirroring `ListReadingPlansRequest`, and a `toFilter(): OlympiadThemeFilter` helper.
- [ ] 12. Create `App\Http\Requests\Olympiad\ShowOlympiadThemeRequest` validating `language` and `seed` (nullable integer, min 1). `prepareForValidation()` parses `{chapters}` via `ChapterRange::fromSegment()` and stashes it on the request. Expose `toDomainRequest(): OlympiadThemeRequest`.
- [ ] 13. Create `App\Http\Resources\Olympiad\OlympiadThemeResource`, `OlympiadQuestionResource`, `OlympiadAnswerResource`, and `OlympiadThemeQuestionsResource` (ResourceCollection with `seed` in meta). Unit test each Resource's JSON shape against a factory-built model.
- [ ] 14. Create `App\Http\Controllers\Api\V1\Olympiad\ListOlympiadThemesController` (`__invoke(ListOlympiadThemesRequest, ListOlympiadThemesAction)` → resource collection with `Cache-Control` header).
- [ ] 15. Create `App\Http\Controllers\Api\V1\Olympiad\ShowOlympiadThemeController` (`__invoke(ShowOlympiadThemeRequest, FetchOlympiadThemeQuestionsAction)` → `OlympiadThemeQuestionsResource` with `Cache-Control` header).
- [ ] 16. Register the two routes in `routes/api.php` under a new `prefix('olympiad')->middleware(['api-key-or-sanctum', 'resolve-language'])` group with named routes (`olympiad.themes.index`, `olympiad.themes.show`).
- [ ] 17. Feature test `ListOlympiadThemesControllerTest`: auth required (401 without key/token), language filter filters correctly, pagination meta present, `Cache-Control` header set.
- [ ] 18. Feature test `ShowOlympiadThemeControllerTest`: happy path returns questions + answers in `data`, `meta.seed` populated, same `seed` replay → identical ordering, missing theme → 404, malformed `{chapters}` → 422, unknown language → 404 (no silent fallback), `Cache-Control` header set.
- [ ] 19. Run `make lint-fix`, `make stan`, then `make test --filter=Olympiad`; follow with the full suite before marking the story ready for review.

## Risks & notes

- **Split recommendation: not required.** Scope is two read-only endpoints over a self-contained table pair. No admin CRUD, no submission flow, no leaderboards. Size is comparable to MBA-006 (parser) + a thin HTTP layer and well below MBA-004. Keep as a single story.
- **Deferred Extractions register — no impact.** No owner-`authorize()` gates here (all routes are public-ish behind api-key-or-sanctum). Counter stays at 4.
- **`mt_srand` is process-global.** `SeededShuffler` sets and consumes it in the same call; any code that relies on PHP's rand state between these calls would be affected. This is a theoretical concern for a stateless request — noted for Review. A future refactor could swap to a seeded `\Random\Randomizer` (PHP 8.2+), but Symfony parity argues for `mt_srand` today.
- **Composite index ordering.** `(language, book, chapters_from, chapters_to)` is chosen because every query filters by language first. The `themes()` projection benefits from this ordering via a loose index scan; confirm EXPLAIN output during engineering if performance is suspect.
- **`is_correct` exposure.** Exposing the correct answer in the API is an intentional Symfony-parity decision (story §Technical Notes). If product later wants to hide it, add a `POST /submit` endpoint in a follow-up story rather than gating this response.
- **Symfony table names.** Story references `olympiad_question` / `olympiad_answer` (singular). Using Laravel conventional plural `olympiad_questions` / `olympiad_answers` here — the shared-DB MBA-005 decision is about the users table specifically; new domain tables follow Laravel convention. Confirm during engineering that the shared DB does not already host singular-named olympiad tables (if it does, the migration must adopt Symfony names instead — flagged as a verification step in task 1).
- **No route-model binding on `{book}`.** Book abbreviations are free strings (source of truth is `BibleBookCatalog` in `App\Domain\Reference`). Validation of the book abbrev against the catalog happens in `ShowOlympiadThemeRequest::rules()` via an inline `Rule::in(BibleBookCatalog::abbrevs())` — add a tiny `abbrevs()` static to the catalog in task 12 if it doesn't already exist.
- **Helper consumer mapping.** Every helper listed above has a task that consumes it: `ChapterRange` (tasks 4, 12), `SeededShuffler` (tasks 6, 10), `OlympiadQuestionQueryBuilder` (tasks 3, 9, 10), both DTOs (tasks 7, 11, 12, 14, 15). No dead code.
