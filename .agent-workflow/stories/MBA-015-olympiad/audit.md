# Audit — MBA-015 Olympiad

## Verdict

**PASS**

Review was APPROVE with 0 Critical, 2 acknowledged Warnings, 3 Suggestions.
Fresh audit scan confirms: no Critical or new Warning issues. All 32 Olympiad
tests and full suite (407/1276) pass. No code changes required.

## Review items

### Warnings (both acknowledged, deferred by design)

- **W1. Per-question answer shuffle reuses request seed** (`FetchOlympiadThemeQuestionsAction:36`).
  — **Skipped.** Plan §Risks and story AC 6 explicitly call for stable replay
  under a single request seed. `is_correct` is exposed per Symfony parity
  (story §Technical Notes), so position-leak is already out of the threat
  model. Future follow-up when/if product hides `is_correct`.

- **W2. `SeededShuffler` reseeds `mt_srand()` after use** (`SeededShuffler:34`).
  — **Skipped.** Plan §Risks flags the `\Random\Randomizer` migration as a
  future refactor. Current behaviour is defensive (downstream `mt_rand()`
  calls are not trivially predictable) and localised.

### Suggestions

- **S1.** `OlympiadThemeNotFoundException::forTheme()` factory.
  — **Deferred.** Payload is `{ message }` only; no current need.

- **S2.** Comment on `prepareForValidation()` precedence in `ShowOlympiadThemeRequest`.
  — **Deferred.** The existing comment (lines 28–30) already notes the 422
  mapping. Book/chapter precedence is implied by PHP evaluation order.

- **S3.** Dedup `CACHE_CONTROL` constant across both controllers.
  — **Deferred.** Two copies is under the abstraction threshold; the review
  itself recommends waiting for a third endpoint.

## Fresh scan

Walked the diff (37 files, +1812 lines) and re-read the hot spots:
`FetchOlympiadThemeQuestionsAction`, `SeededShuffler`, `ChapterRange`,
`ShowOlympiadThemeRequest`, both controllers, `OlympiadQuestionQueryBuilder`,
`ListOlympiadThemesAction`, `OlympiadThemeQuestionsResource`. No new
concerns. Models use `$guarded = []` with typed casts; `newEloquentBuilder`
wires the custom builder; exception handler mappings are in place at
`bootstrap/app.php`; routes are under `api-key-or-sanctum` +
`resolve-language` middleware. `is_correct` exposure is intentional and
documented.

## Verification runs

- `make test filter=Olympiad` — 32 passed / 123 assertions / 0.73s.
- `make lint-fix` — PASS on 287 files.
- `make stan` — OK on 267 files.
- `make test` (full) — 407 passed / 1276 assertions / 7.27s.

## Outcome

All Critical/Warning items resolved (Warnings acknowledged as design
intent). Story status transitions `qa-passed` → `done`.
