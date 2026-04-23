# QA: MBA-014-bible-collections

**QA:** QA Agent
**Branch:** `mba-014`
**Verdict:** QA PASSED

## Test results

- `make test filter=Collection` — **24 passed / 104 assertions / 0.65s**.
- `make test` (full) — **389 passed / 1241 assertions / 7.21s**. No regressions.

## AC → test coverage

| AC | Requirement | Evidence |
|---|---|---|
| 1 | `GET /api/v1/collections` paginated, language filter, protected | `ListCollectionTopicsTest.php:25-38,40-53,68-82,128-132,134-139` |
| 1 | `reference_count` on list | `ListCollectionTopicsTest.php:55-66` |
| 2 | `Cache-Control: public, max-age=3600` on list | `ListCollectionTopicsTest.php:115-126` |
| 3 | `GET /api/v1/collections/{topic}` with parsed refs + display_text | `ShowCollectionTopicTest.php:26-74` |
| 3 | `parsed` shape mirrors MBA-006 VO | `ShowCollectionTopicTest.php:70-73`; `ResolveCollectionReferencesActionTest.php:33-36` |
| 3 | show endpoint cache header | `ShowCollectionTopicTest.php:131-142` |
| 4 | Graceful degradation on malformed ref (no 500, parse_error set, siblings parsed) | `ShowCollectionTopicTest.php:76-113`; `ResolveCollectionReferencesActionTest.php:41-66` |
| 4 | Warning logged on degraded row | `ShowCollectionTopicTest.php:112`; `ResolveCollectionReferencesActionTest.php:65` |
| 5 | `404` on unknown topic; `404` cross-language | `ShowCollectionTopicTest.php:115-122,124-129` |
| 6 | Feature tests: list/filter/show happy/malformed/404 | covered above |
| 7 | Unit tests for `ResolveCollectionReferencesAction` | `ResolveCollectionReferencesActionTest.php:19-105` (valid, malformed+log, RO formatting, empty, multi-ref) |

Additional covered: references ordered by position (`ShowCollectionTopicTest.php:152-177`), list ordered by position (`ListCollectionTopicsTest.php:84-94`), `per_page` bounds validation (`ListCollectionTopicsTest.php:96-113`), unknown api-key rejection (`ListCollectionTopicsTest.php:134-139`).

## Review follow-up

Review verdict was APPROVE with 5 non-blocking suggestions (redundant index, helper extraction, factory naming, `ShowCollectionTopicRequest` `language` rule, `display_text` separator glyph). None are Critical. No outstanding blockers.

## Verdict

All ACs covered with tests, full suite green, no Critical review items open — **QA PASSED**.
