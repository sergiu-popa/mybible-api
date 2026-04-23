# Story: MBA-015-olympiad

## Title
Bible olympiad — themed quiz questions

## Status
`done`

## Description
A public quiz endpoint serving trivia-style questions keyed by Bible
book + chapter range + language. Used by the olympiad UI to run timed
quizzes. Read-only for users — admin authors the questions elsewhere.

Symfony source:
- `OlympiadController::themes()` — list theme bundles (book + chapter
  range combinations)
- `OlympiadController::questions()` — fetch questions for a theme

Maps to `olympiad_question` table (and probably `olympiad_answer` —
confirm in architecture).

## Acceptance Criteria

### Theme listing
1. `GET /api/v1/olympiad/themes?language={iso2}` returns available
   quiz themes.
   - Response: `{ data: [{ id, book, chapters_from, chapters_to,
     language, question_count }, ...] }`.
   - `book` is the book abbreviation (string).
   - Paginated, default 50/page.
2. Protected by `api-key-or-sanctum`.
3. `Cache-Control: public, max-age=3600`.

### Questions for a theme
4. `GET /api/v1/olympiad/themes/{book}/{chapters}?language={iso2}`
   returns questions.
   - `chapters` is a range or single chapter (`1-3`, `5`).
   - Response: `{ data: [{ id, question, answers: [{ id, text,
     is_correct }], explanation? }, ...] }`.
   - Questions randomized server-side (seeded by request so repeats
     remain stable within a single session — a `seed` query param is
     honored).
5. `404` when no questions match the theme.
6. Answer ordering randomized per question with the same seed.

### Tests
7. Feature tests: theme listing (language filter), questions happy
   path, randomization stability with seed, missing theme 404,
   language fallback behavior (404, not silent fallback).
8. Unit tests for the Actions, including seed-based shuffle
   determinism.

## Scope

### In Scope
- Two endpoints as listed.
- `OlympiadQuestion` (+ `OlympiadAnswer` if separate) Eloquent models.
- Actions, API Resources, Feature tests.

### Out of Scope
- Admin authoring endpoints.
- Submitting answers / tracking user score (distinct domain if product
  wants it — not currently in Symfony).
- Leaderboards.

## Technical Notes

### Seed-based randomization
`mt_srand($seed)` before `shuffle()` is non-cryptographic but fine for
UX randomization. The Action accepts an `int $seed` from the request
(or generates a random one and echoes it back in the response envelope
for client re-use).

### is_correct exposure
Revealing `is_correct` in the API response is the Symfony behavior.
Clients handle the "don't show the answer until user submits" logic.
If product wants to hide it and add a separate `POST /submit-answer`
endpoint, that's a follow-up story.

### Chapter range parsing
Reuse MBA-006's chapter range parser for the `{chapters}` path
segment rather than implementing a fresh regex.

## Dependencies
- **MBA-005** (auth + users).
- **MBA-006** (chapter-range parsing).

## Open Questions for Architect
1. **Answer model shape.** Confirm whether answers are a child table
   or serialized JSON on `olympiad_question`.
2. **is_correct visibility.** Keep Symfony behavior (expose) or
   introduce a server-side check flow. Recommend keep for MVP.
3. **Language fallback.** 404 vs fall back to another language.
   Recommend 404.
