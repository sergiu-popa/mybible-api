# Story: MBA-014-bible-collections

## Title
Bible collections — themed reference lists

## Status
`done`

## Description
A "collection" is a topical list of Bible references (e.g. "Verses about
patience"). Admin curates them; clients render the list with linkified
references. This is the first consumer of MBA-006 (reference parser) on
the HTTP surface.

Symfony source:
- `CollectionController::show()` — fetch a collection topic with its
  references

Maps to `collection_topic` and `collection_reference` tables.

## Acceptance Criteria

### List topics
1. `GET /api/v1/collections` returns paginated collection topics for a
   language.
   - Supports `?language={iso2}` (defaults to resolved language).
   - Response: `{ data: [{ id, name, description?, language,
     reference_count }, ...] }`.
   - Protected by `api-key-or-sanctum`.
2. `Cache-Control: public, max-age=3600`.

### Show topic with references
3. `GET /api/v1/collections/{topic}` returns the topic with its
   references expanded.
   - Response: `{ data: { id, name, description, language, references:
     [{ raw, parsed: { book, chapter, verses }, display_text }, ...] } }`.
   - `parsed` is the MBA-006 output for the raw reference string.
   - `display_text` is the human-readable form
     (`ReferenceFormatter::toHumanReadable`).
4. If a stored reference fails to parse, do not blow up the whole
   response — include it in the list with `parsed: null` and a
   `parse_error` field. Log a warning so admin can fix the data.
5. `404` on unknown topic id.

### Tests
6. Feature tests: list with language filter, show topic happy path,
   show topic with one malformed reference (graceful degradation), 404.
7. Unit tests for `ResolveCollectionReferencesAction`.

## Scope

### In Scope
- Two endpoints: list and show.
- `CollectionTopic`, `CollectionReference` Eloquent models.
- `ResolveCollectionReferencesAction` bridging stored strings to
  parsed/display forms.
- API Resources, Feature tests.

### Out of Scope
- Admin CRUD on collections.
- User-owned collections (Symfony does not have this).
- Linkification to live in the response body — MBA-006 provides
  `ReferenceCreator::linkify` if clients want HTML, but the default
  API response returns structured JSON, not HTML. Clients build the
  tappable UI themselves.

## Technical Notes

### Reference parsing on read
Each collection reference is parsed at response-build time via
`ReferenceParser`. For a collection with 50 references this is 50 cheap
parse calls — acceptable. If a collection grows past ~200 references
and we see latency, cache the parsed form in a JSON column on
`collection_reference` at admin-write time. Deferred.

### Graceful degradation
If parsing fails, the Symfony app also shows the raw string. Preserve
that — killing the whole endpoint because one row in a 30-row list has
a typo is a worse user experience than showing 29 parsed refs + 1 raw
one.

## Dependencies
- **MBA-005** (auth).
- **MBA-006** (reference parser — HARD dependency).
- **MBA-007** (book/chapter catalog for parser validation).

## Open Questions for Architect
1. **Language fallback.** If RO is asked and only EN exists for a
   topic, 404 or fall back to EN? Recommend 404 (consistent with
   MBA-008 default).
2. **Description format.** Plain text vs HTML. Recommend plain text;
   trust admin content if HTML.
3. **Paginating references within a topic.** Topics likely have <50
   refs — no pagination needed. Confirm the max count in the Symfony
   data before finalizing.
