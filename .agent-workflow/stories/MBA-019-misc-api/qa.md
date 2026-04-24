# QA: MBA-019-misc-api

**Commit under QA:** `303ef8b` (review APPROVE → qa-ready) on top of `f175b08`
**Branch:** `mba-019`
**Verdict:** **QA PASSED** → `qa-passed`

## Test suite

- `make test` (full) — **678 passed, 2055 assertions**, 11.19 s.
- `make test filter='News|QrCode|MobileVersion'` — **58 passed, 150 assertions**, 1.12 s.
- No skipped, risky, or incomplete tests.

## Acceptance-criteria coverage

### News (AC 1–4)

| AC | Covering test(s) |
|---|---|
| 1. Paginated, newest-first, default 20 / max 50 | `ListNewsTest::test_it_returns_paginated_news_newest_first`, `test_it_honours_per_page`, `test_it_rejects_per_page_above_the_max` |
| 2. JSON shape `{ data: [...] }` with documented keys | `test_it_returns_paginated_news_newest_first` (asserts `assertJsonStructure`) + `NewsResourceTest` unit |
| 3. `Cache-Control: public, max-age=300` | `test_it_sets_public_cache_headers` |
| 4. Behind `api-key-or-sanctum` | `test_it_accepts_sanctum_auth`, `test_it_rejects_missing_credentials` |

Edge cases also covered: language fallback from `ResolveRequestLanguage` middleware; unpublished/future rows excluded.

### QR codes (AC 5–8)

| AC | Covering test(s) |
|---|---|
| 5. `GET /qr-codes?reference=...` happy path | `ShowQrCodeTest::test_it_returns_the_qr_metadata_for_a_known_reference` |
| 6. Shape `{ data: { reference, url, image_url } }` | same happy-path test + `QrCodeResourceTest` unit |
| 7. Missing QR → 404 (serve-only per plan) | `test_it_returns_404_when_no_stored_qr_exists` |
| 8. `Cache-Control: public, max-age=86400` | `test_it_sets_public_cache_headers` |

Edge cases: 422 on unparseable reference, 422 on multi-reference input, 422 on missing `reference`, Sanctum auth path, 401 without credentials.

### Mobile version (AC 9–11)

| AC | Covering test(s) |
|---|---|
| 9. Response shape with verbatim field names | `ShowMobileVersionTest::test_it_returns_ios_version_metadata`, `test_it_returns_android_version_metadata` + `MobileVersionResourceTest` unit |
| 10. `X-Api-Key` alone suffices | `test_api_key_alone_is_sufficient` + `test_it_rejects_missing_credentials` |
| 11. `Cache-Control: public, max-age=300` | `test_it_sets_public_cache_headers` |

Edge cases: 422 on missing `platform`, 422 on unknown platform.

### Tests (AC 12–13)

- Feature tests exist for all three endpoints and cover happy + failure paths. ✓
- Unit tests exist for Models, QueryBuilders, Form Requests, and Resources. ✓ (No Actions planned; plan explicitly omitted them — see plan §Domain layout.)

## Regression probe

- Full suite (678 tests) green, including MBA-005 (auth), MBA-006 (reference parsing — reused by `ShowQrCodeRequest`), MBA-013 (language middleware — reused by `ListNewsTest`). No adjacent feature regressed.

## Review findings follow-up

- 0 Critical, 0 Warning. 5 Suggestions noted for future polish — non-blocking per reviewer.

## Verdict

**QA PASSED.** All ACs verified by passing tests, happy + failure + auth paths exercised, no regressions in the wider suite. Move status → `qa-passed`.
