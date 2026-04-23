# Audit: MBA-014-bible-collections

**Auditor:** Auditor
**Branch:** `mba-014`
**Verdict:** PASS
**Status:** `done`

## Summary

Holistic pass over the two new read-only collection endpoints, the
`App\Domain\Collections` domain (models, QueryBuilder, DTO, Action), the
migration, Form Requests, Resources, and feature + unit tests. Code reviewer
and QA already covered the main surfaces; this audit addresses the Review
suggestions and re-ran the gate.

## Gates

- `make lint-fix` — clean (274 files).
- `make stan` — no errors (254 files).
- `make test filter=Collection` — 24 passed / 104 assertions.
- `make test` — 389 passed / 1241 assertions. No regressions.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | Redundant single-column `language` index duplicated by the composite `(language, position)` index (prefix already covers `language`-only lookups). | `database/migrations/2026_04_22_211556_create_collection_topics_and_references_tables.php:21` | Warning | Fixed | Dropped `$table->index('language')`; kept the composite `(language, position)` which covers both access patterns. |
| 2 | Defensive `instanceof Language` guard after `ResolveRequestLanguage::ATTRIBUTE_KEY` default already returns a `Language`. | `app/Http/Controllers/Api/V1/Collections/ListCollectionTopicsController.php:23-26`, `ShowCollectionTopicController.php:29-30` | Suggestion | Deferred | Pattern is load-bearing for PHPStan narrowing; Review flagged the refactor for a third call site. No action until a helper is warranted. |
| 3 | Factory state names `valid()` / `malformed()` drifted from plan's `withValidReferences()` / `withMalformedReference()`. | `database/factories/CollectionReferenceFactory.php:30-38` | Suggestion | Skipped | Shorter names are clearer; plan naming was indicative not binding. |
| 4 | `ShowCollectionTopicRequest` declares `language` rule though plan said "no rules beyond the route binding". | `app/Http/Requests/Collections/ShowCollectionTopicRequest.php:19-24` | Suggestion | Skipped | Rule is harmless (`nullable\|string`) and aligns with `ListCollectionTopicsRequest`. Tightening to an enum list is a cross-cutting change better addressed alongside the other `language` accepting requests. |
| 5 | `implode('; ', …)` glyph choice for joining human-readable sub-references is hard-coded. | `app/Domain/Collections/Actions/ResolveCollectionReferencesAction.php:86` | Suggestion | Deferred | No i18n signal yet; revisit when a language-specific separator is needed. |

## Risks

None open. Migration has not been deployed; index change is schema-safe.

## Verdict

All Critical + Warning findings resolved. Suggestions accounted for (Fixed /
Skipped / Deferred with reason). Status advanced to `done`.
