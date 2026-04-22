# Story: MBA-008-verses-and-daily-verse

## Title
Verses lookup and daily verse

## Status
`planned`

## Description
Clients need two things on top of the catalog (MBA-007): pull a specific
verse or verse range on demand, and fetch the "verse of the day" that the
app surfaces on its home screen.

Symfony source:
- `VerseController::show()` / `byReference()` — verse retrieval
- `DailyVerseController::today()` — verse of the day

Daily verse is a pre-seeded mapping of `date → verse` maintained by the
Admin (Admin itself is out of scope, so the Laravel port reads the same
table the Admin already writes to).

## Acceptance Criteria

### Verse retrieval
1. `GET /api/v1/verses?reference=GEN.1:1-3.VDC` returns the requested
   verse(s) using the canonical reference form parsed by MBA-006.
2. Response shape: `{ data: [{ version, book, chapter, verse, text }, ...] }`
   — not paginated (bounded by the requested range).
3. Alternatively: `GET /api/v1/verses?book=GEN&chapter=1&verses=1-3,5&version=VDC`
   accepts parameters individually and assembles them before delegating to
   the parser. At most one of `reference` or the split params must be
   provided (`422` otherwise).
4. `version` is optional; defaults to the user's preferred version from
   profile (MBA-018) or the language default from config.
5. Returns `422` with a descriptive error when the reference is malformed
   (bubbles up `InvalidReferenceException` from MBA-006).
6. Returns `404` when the book/chapter exists but the verse(s) are missing
   in that version. Returns the partial set it found with a `meta.missing`
   array listing the unresolved verse numbers — preferred over 404 when
   *some* verses resolved.
7. Protected by `api-key-or-sanctum`.

### Daily verse
8. `GET /api/v1/daily-verse?language={iso2}` returns the daily verse for
   today in the given language.
9. `date` query param (`YYYY-MM-DD`) is supported for retrieving the daily
   verse of any specific date (clients re-render old home screens).
10. If no daily verse is configured for `{language, date}`, returns `404`
    with body `{ message: "No daily verse for this date/language." }`.
11. Response shape: `{ data: { date, reference, version, text, source } }`.
12. `Cache-Control: public, max-age=3600`. Clients poll frequently; give
    them a cheap hit.

### Tests
13. Feature tests for: single verse, multi-verse range, mixed-verse
    references, unknown reference (422), partial resolution (meta.missing
    populated), default version resolution for logged-in user vs anonymous.
14. Feature tests for daily verse: today, past date, missing daily verse,
    language fallback behavior.
15. Unit tests for any new Actions (`ResolveVersesAction`,
    `GetDailyVerseAction`).

## Scope

### In Scope
- Two endpoints: `GET /api/v1/verses`, `GET /api/v1/daily-verse`.
- `Verse` model + query scopes for reference-based retrieval.
- `DailyVerse` model (maps to the existing Symfony table — confirm table
  name in architecture doc).
- Actions: `ResolveVersesAction`, `GetDailyVerseAction`.
- Form Requests for query param validation.

### Out of Scope
- Admin write endpoints for daily verse.
- Verse highlighting / annotations (those live under Sabbath School or a
  separate future domain).
- Search across verse text (separate future story if product asks).

## Technical Notes

### Reference resolution
`ResolveVersesAction` takes a `Reference` (from MBA-006) and queries
`verses` with a compound `WHERE (version, book, chapter, verse IN [...])`.

For multi-reference inputs (`Multiple` parser), batch the IN clauses by
`(version, book, chapter)` so we don't issue one query per verse.

### Partial resolution
Preferred over 404 because clients often request ranges that straddle
chapters they haven't realized don't exist in a version (e.g. deuterocanon
in one version but not another). The `meta.missing` array is cheap to
compute and much kinder to the client.

### Default version resolution
Three-tier fallback:
1. Explicit `version` query param.
2. Authenticated user's `preferred_version` (added in MBA-018).
3. `config('bible.default_version_by_language')` lookup.

If none resolves, reject with `422` (`"Version required."`).

## Dependencies
- **MBA-006** (reference parser).
- **MBA-007** (Bible catalog — shares `Verse` and `Version` models).

## Open Questions for Architect
1. **Partial resolution status code.** 200 with `meta.missing`, or 207
   (Multi-Status)? Recommendation: 200 with `meta.missing` — 207 is
   rarely understood by clients.
2. **Daily verse table schema.** Is it a single row per `(language, date)`
   or does it support variants? Inspect the Symfony `daily_verse` /
   similar table before modeling.
3. **Language fallback for daily verse.** If RO is requested but only EN
   is configured for today, do we fall back to EN or return 404?
   Recommendation: 404 (clients should know what language they asked for).
