# Code Review: MBA-015-olympiad

**Verdict:** APPROVE
**Counts:** Critical 0 / Warning 2 (both acknowledged) / Suggestion 3

All `make lint`, `make stan`, and `make test --filter=Olympiad` (32 tests, 123
assertions) pass. The implementation matches `plan.md` end-to-end: domain
layout, DTOs, Action seams, Resource shapes, route wiring, and exception
renderer registrations all landed as planned.

## Warnings

- [x] `app/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsAction.php:41-44`
  — every question's answer shuffle reuses the request-level `$seed`, so two
  questions with the same answer count will be shuffled to the **same
  permutation** within a single response. Combined with the intentional
  `is_correct` exposure (Symfony parity, story §Technical Notes), this means
  "the correct answer keeps landing in the same slot index across questions"
  in some runs. Mitigation would be to derive a per-question seed (e.g.
  `$seed ^ $question->id` passed into `SeededShuffler::shuffle()`), which
  preserves replayability while decorrelating per-question orderings.
  — acknowledged: story AC 6 reads "Answer ordering randomized per question
  with the same seed", which the Engineer and plan interpret as stable
  replay under the same request seed rather than decorrelated per-question
  seeds; `is_correct` is exposed anyway so position-leak is already out of
  the threat model. Worth revisiting if the UI ever wants to hide
  `is_correct` (flagged in story §Technical Notes as a future follow-up).

- [x] `app/Domain/Olympiad/Support/SeededShuffler.php:33-36` — after
  consuming the caller's seed the class calls `mt_srand()` (no arg) to
  reseed with a non-deterministic value. That is fine for current callers
  but leaves `mt_rand()` globally re-seeded as a side effect of a
  `shuffle()` call, which is surprising for anyone reusing the class later.
  A future refactor to `\Random\Randomizer` (PHP 8.2+) with a `Mt19937`
  engine would remove the global state entirely.
  — acknowledged: matches plan §Risks & notes bullet on `mt_srand`; the
  deferral to `\Random\Randomizer` is already called out there as a follow-up.

## Suggestions

- `app/Domain/Olympiad/Exceptions/OlympiadThemeNotFoundException.php:10-13`
  — the constructor fixes the default message. Consider a named static
  factory (`::forTheme(OlympiadThemeRequest $r)`) if richer context is ever
  needed; currently fine because the 404 payload is `{ message }` only.

- `app/Http/Requests/Olympiad/ShowOlympiadThemeRequest.php:30` —
  `prepareForValidation()` throws `InvalidReferenceException` before
  `rules()` runs, so a request with both a malformed `{chapters}` and an
  unknown `{book}` surfaces only the chapter error. Acceptable, but a brief
  comment noting the precedence would help future readers.

- `app/Http/Controllers/Api/V1/Olympiad/ListOlympiadThemesController.php:14`
  and `.../ShowOlympiadThemeController.php:14` — both controllers declare an
  identical `private const CACHE_CONTROL = 'public, max-age=3600'`. Two
  copies is under the dedup threshold; if a third public cache endpoint
  shows up, lift this to a shared trait/middleware rather than a third
  constant.

## Checks against plan

- Tasks 1–19 all implemented as described; exception renderer for
  `OlympiadThemeNotFoundException` registered at
  `bootstrap/app.php:91` alongside the existing `InvalidReferenceException`
  handler (no new `InvalidReferenceException` renderer was needed — the
  pre-existing one at `bootstrap/app.php:82` already emits
  `errors.reference`, and the feature test asserts exactly that shape).
- Composite index `(language, book, chapters_from, chapters_to)` landed on
  `olympiad_questions`
  (`database/migrations/2026_04_22_212447_create_olympiad_questions_and_answers_table.php:15-18`).
- `ChapterRange` VO lives under `App\Domain\Reference` as planned, with the
  `fromSegment()` surface throwing `InvalidReferenceException::unparseable`
  — consistent with the existing `Parser\ChapterRangeParser` error surface.
- `is_correct` exposure is intentional (Symfony parity, story §Technical
  Notes, plan §Risks bullet).
- Deferred Extractions register is untouched (no new copies of the
  owner-`authorize()` pattern or lifecycle `withProgressCounts()` helper).

## Tests

All 32 new tests pass:
- `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php`
- `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php`
- `tests/Unit/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsActionTest.php`
- `tests/Unit/Domain/Olympiad/Actions/ListOlympiadThemesActionTest.php`
- `tests/Unit/Domain/Olympiad/QueryBuilders/OlympiadQuestionQueryBuilderTest.php`
- `tests/Unit/Domain/Olympiad/Support/SeededShufflerTest.php`
- `tests/Unit/Domain/Reference/ChapterRangeTest.php`
- `tests/Unit/Http/Resources/Olympiad/OlympiadQuestionResourceTest.php`
- `tests/Unit/Http/Resources/Olympiad/OlympiadThemeResourceTest.php`

Coverage hits every AC: auth, language filter, pagination, happy path +
seed replay, 404 missing theme, 422 malformed chapters/unknown book, 404
on language not present (no silent fallback), cache header, single-chapter
path, Action unit behaviour for empty / same-seed / different-seed /
omitted-seed.

## Status

Setting `story.md` status to `qa-ready`.
