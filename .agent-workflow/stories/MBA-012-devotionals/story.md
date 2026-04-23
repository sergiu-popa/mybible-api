# Story: MBA-012-devotionals

## Title
Daily devotionals and personal favorites

## Status
`qa-passed`

## Description
Clients show a daily devotional ("adults" or "kids" variants) keyed by
language and date. Users can also mark specific devotionals as favorites
for later retrieval.

Symfony source:
- `DevotionalController::byTypeAndLanguage()` — fetch today's (or a
  specific date's) devotional
- `DevotionalFavoriteController::index/toggle()` — user favorites

Admin populates the `devotional` table; the API only reads it.

## Acceptance Criteria

### Fetch devotional
1. `GET /api/v1/devotionals?language={iso2}&type={adults|kids}` returns
   today's devotional in the requested language and variant.
2. `date` query param optional (`YYYY-MM-DD`) — returns the devotional
   for that specific date.
3. Response: `{ data: { id, date, type, language, title, content,
   passage?, author? } }`.
4. `404` when no devotional exists for the
   `(date, language, type)` tuple.
5. Protected by `api-key-or-sanctum` (public clients can fetch today's
   devotional; logged-in users get the same endpoint).
6. `Cache-Control: public, max-age=3600`.

### List devotionals (archive)
7. `GET /api/v1/devotionals/archive?language={iso2}&type={adults|kids}`
   returns a paginated list (newest first) of devotionals published up
   to today. Max page size 30.
8. Supports optional `?from=YYYY-MM-DD&to=YYYY-MM-DD` window.

### Favorites
9. `GET /api/v1/devotional-favorites` — paginated list of the caller's
   favorite devotionals, with the full devotional embedded (not just the
   id). Sanctum required.
10. `POST /api/v1/devotional-favorites/toggle` — body `{ devotional_id }`
    flips favorite state. Returns `201` on create, `200` on delete with
    `{ deleted: true }`.
11. Cross-user access blocked (owner-only list).

### Tests
12. Feature tests: fetch today, fetch past date, fetch missing date
    (404), both types, both languages (RO at minimum, HU if Symfony has
    the data), archive pagination, archive date window.
13. Favorite tests: toggle insert, toggle remove, cross-user access.
14. Unit tests for the Actions.

## Scope

### In Scope
- Three endpoints: `devotionals`, `devotionals/archive`,
  `devotional-favorites` (+ toggle).
- `Devotional` + `DevotionalFavorite` Eloquent models.
- Actions, DTOs, Form Requests, API Resources, Feature tests.

### Out of Scope
- Admin devotional authoring flow.
- Rich-text / HTML in devotional content — take whatever Symfony stores,
  pass through.
- Push notifications for "new devotional today" — a separate concern.

## Technical Notes

### Type enum
`adults` and `kids`. Model as a PHP 8.1 enum
(`App\Domain\Devotional\DevotionalType`) and cast on the Eloquent model.
Reject unknown values at Form Request level with `Rule::enum()`.

### Multi-language fallback
None. If RO is asked and only HU exists for today, return 404. Symfony
behaves the same way; clients handle it.

### Favorite toggle shape
Same pattern as MBA-009 `reading-progress/toggle` — reuse the pattern
and keep response envelope consistent.

## Dependencies
- **MBA-005** (auth + users).
- No dependency on MBA-006 — passage in devotionals is a freeform
  string, not a parsed reference. If product wants parsing for
  cross-linking, push to a follow-up story.

## Open Questions for Architect
1. **Archive date range default.** If `from/to` omitted, return
   everything (paginated)? Or last 90 days? Recommend last 90 days to
   keep the first page fast.
2. **Passage linking.** The `passage` column is currently a free
   string. Worth parsing with MBA-006 at response time to link into
   `/api/v1/verses`? Nice to have, not MVP.
3. **Devotional content HTML.** If Symfony stores HTML, do we sanitize
   on the way out, or trust the admin who wrote it? Recommend trusting
   (admin authenticated, content reviewed).
