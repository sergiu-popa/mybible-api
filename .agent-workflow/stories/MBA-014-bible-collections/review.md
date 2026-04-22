# Review: MBA-014-bible-collections

**Reviewer:** Code Reviewer
**Branch:** `mba-014`
**Verdict:** APPROVE
**Status:** `qa-ready`

## Summary

Implementation matches the plan end-to-end. Two read-only endpoints
(`GET /api/v1/collections`, `GET /api/v1/collections/{topic}`) are wired under
the `api-key-or-sanctum` + `resolve-language` middleware stack, with a new
`App\Domain\Collections` domain containing models, a QueryBuilder, a readonly
DTO, and the `ResolveCollectionReferencesAction` that encapsulates per-row
try/catch around `ReferenceParser::parse()` and `ReferenceFormatter::toHumanReadable()`.
Language scoping on `{topic}` is enforced model-side via `resolveRouteBinding()`
with a fallback that re-parses the query string if the middleware hasn't run
yet. Graceful degradation, logging, and cache headers behave as specified.

All gates pass:
- `make lint` — clean (274 files)
- `make stan` — no errors (254 files)
- `make test filter=Collection` — 24 tests, 104 assertions

## Findings

### Suggestions

- [ ] **Redundant single-column `language` index.**
  `database/migrations/2026_04_22_211556_create_collection_topics_and_references_tables.php:22-23`
  The composite `(language, position)` already covers single-column `language`
  lookups as a prefix. The standalone `$table->index('language')` on line 22 is
  redundant and will only cost us an extra B-tree at write time. Drop it and
  keep the composite.

- [ ] **Defensive-only instanceof check in list controller.**
  `app/Http/Controllers/Api/V1/Collections/ListCollectionTopicsController.php:22-25`
  `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)`
  already returns `Language::En` when the attribute is absent. The downstream
  `$language instanceof Language ? $language : Language::En` guard is belt-and-
  suspenders over that — fine for PHPStan narrowing, but worth a two-line helper
  (`RequestLanguage::from($request)`) so the next endpoint that needs the same
  read doesn't re-introduce the duplication. Defer until a third call site.

- [ ] **Factory state naming drifted from plan.**
  `database/factories/CollectionReferenceFactory.php:28-38`
  Plan specified `withValidReferences()` / `withMalformedReference()`. Engineer
  shipped shorter `valid()` / `malformed()`. Both work; the shorter names are
  arguably better. Keep as-is; flagging only because downstream readers of the
  plan may grep for the old names.

- [ ] **`ShowCollectionTopicRequest` validates `language` as `nullable|string`.**
  `app/Http/Requests/Collections/ShowCollectionTopicRequest.php:19-23`
  The plan said "no rules beyond the route binding." Current rule is harmless
  (accepts anything, `Language::fromRequest` falls back to En) but technically
  a deviation. Either drop the rule or tighten to an explicit `in:en,ro,hu`
  enum list so invalid language codes fail loud instead of silently falling
  back. Not blocking; intentional-looking.

- [ ] **`implode('; ', …)` for multi-reference `display_text`.**
  `app/Domain/Collections/Actions/ResolveCollectionReferencesAction.php:79-85`
  Choice of `;` separator for joining human-readable sub-references is sensible
  but unspecified in the story. If admin data ever expects a different
  separator for a language, this hard-coded glyph becomes the thing to change.
  Defer; revisit if i18n concerns surface.

## Checklist

- [x] Controllers free of business logic (delegate to `ResolveCollectionReferencesAction` and QueryBuilder).
- [x] Form Requests used for validation; no inline `validate()`.
- [x] Eloquent API Resources wrap responses; no models returned directly.
- [x] Authorization via Form Request `authorize()`; api-key-or-sanctum middleware.
- [x] Feature tests assert JSON structure + status codes (not HTML).
- [x] Exception paths (404 cross-language, 404 unknown) return the JSON envelope.
- [x] Route-model binding language scope tested (`test_it_returns_404_for_cross_language_topic`).
- [x] Graceful degradation on malformed reference tested + logs warning.
- [x] Cache-Control headers tested on both endpoints.
- [x] No N+1 (list uses `withCount`; show uses `load('references')`).
- [x] Composite index on `(collection_topic_id, position)` matches eager-loaded ordered relation.
- [x] DTO is `final readonly`; Action is `final`; types explicit.
- [x] `resolveRouteBinding` follows existing `ReadingPlan` pattern; re-parses query-string language to defend against binding-before-middleware ordering.
