# QA: MBA-027-symfony-parity-catch-all

## Test Results

**Full suite: 1277 tests passed** (all green).

### Acceptance Criteria Coverage

Every acceptance criterion from `story.md` §35–36 has test coverage:

#### Devotional types entity + media (AC §1–7)
- ✅ `tests/Feature/Api/V1/Devotionals/ShowDevotionalEndpointTest.php` — legacy enum string resolves, slug resolves, language-specific type resolution wins, unknown slug 404, duplicate `(language, type_id, date)` impossible
- ✅ `tests/Feature/Api/V1/Admin/AdminDevotionalTypesTest.php` — CRUD + reorder + delete blocked when type has devotionals + 401/403 auth boundaries

#### Mobile versions DB-backed (AC §8–12)
- ✅ `tests/Feature/Api/V1/Mobile/ShowMobileVersionTest.php` — existing config-shape keys preserved, cache works
- ✅ `tests/Feature/Api/V1/Admin/AdminMobileVersionsTest.php` — CRUD + UNIQUE `(platform, kind)` collision returns 422
- ✅ Migration test — seed rows exist with expected triples, re-run is idempotent

#### Collections parent + image (AC §13–17)
- ✅ `tests/Feature/Api/V1/Collections/ListCollectionsEndpointTest.php` — public list by language
- ✅ `tests/Feature/Api/V1/Collections/ShowCollectionEndpointTest.php` — detail with topics, 404 unknown slug
- ✅ `tests/Feature/Api/V1/Collections/ShowCollectionTopicEndpointTest.php` — cross-collection topic 404 via scopeBindings
- ✅ `tests/Feature/Api/V1/Admin/AdminCollectionsTest.php` — CRUD both collections + topics + image_cdn_url accepted + FK SET NULL on delete

#### QR codes full Symfony model (AC §18–22)
- ✅ `tests/Feature/Api/V1/QrCode/RecordQrCodeScanEndpointTest.php` — scan endpoint event dispatch, 404 unknown, 204 success
- ✅ `tests/Feature/Api/V1/QrCode/ShowQrCodeEndpointTest.php` — reference lookup still works, NULL-reference rows excluded
- ✅ `tests/Feature/Api/V1/Admin/AdminQrCodesTest.php` — CRUD + UNIQUE `(place, source)` collision returns 422

#### Olympiad parity + user attempts (AC §23–27)
- ✅ `tests/Feature/Api/V1/Olympiad/Attempts/StartOlympiadAttemptTest.php` — start creates attempt + locks question UUIDs
- ✅ `tests/Feature/Api/V1/Olympiad/Attempts/SubmitOlympiadAttemptAnswersTest.php` — idempotent re-submit, cross-theme question UUID returns 422, submit after finish 422, `created_at` preserved on re-submit
- ✅ `tests/Feature/Api/V1/Olympiad/Attempts/FinishOlympiadAttemptTest.php` — score computed, second finish 422
- ✅ `tests/Feature/Api/V1/Olympiad/Attempts/ListUserOlympiadAttemptsTest.php` — pagination + filters + cross-user 404
- ✅ `tests/Feature/Api/V1/Admin/AdminListOlympiadAttemptsTest.php` — auth + filters
- ✅ `tests/Unit/Domain/Olympiad/OlympiadQuestionQueryBuilderMatchingThemeTest.php` — range-only match, chapter-only match, mismatch, single-chapter boundary

#### Notes & Favorites colour (AC §28–31)
- ✅ `tests/Feature/Api/V1/Notes/StoreNoteEndpointTest.php` + `UpdateNoteEndpointTest.php` — accepted `#RRGGBB`, `#RRGGBBAA`, rejected malformed, `null` clearing
- ✅ `tests/Feature/Api/V1/Favorites/CreateFavoriteEndpointTest.php` + `UpdateFavoriteEndpointTest.php` — same coverage

#### News detail + language defaults (AC §32–34)
- ✅ `tests/Feature/Api/V1/News/ShowNewsEndpointTest.php` — happy path, 404 unpublished, 404 unknown, response includes `content`
- ✅ Migration test — news `language='ro'` backfilled, `published_at` from `created_at` preserves order

### Edge Case Probing

- ✅ Backwards-compat enum strings (`devotionals?type=adults` still works alongside slug)
- ✅ Language-specific type resolution (language-specific row wins over global)
- ✅ Olympiad attempt → question membership validation (`matchingTheme` prevents cross-theme answers)
- ✅ Route-model binding scoping (`OlympiadAttempt::resolveRouteBinding` user-scopes; `Collection` scopeBindings prevent cross-collection topic access)
- ✅ Admin auth boundaries (super-admin required; 401/403 tested)
- ✅ UNIQUE constraint violations (422 on collision)
- ✅ Idempotency (re-submit answer, re-run migration idempotent)
- ✅ FK constraints (delete devotional type blocked if devotionals reference it; FK SET NULL on collection delete orphans topics safely)

### Related Feature Regressions

Spot-checks for regressions in nearby features:
- ✅ Existing devotionals list/show still work (type enum conversion to id transparent)
- ✅ Mobile bootstrap still emits correct shape (repository-backed, matches config-shape)
- ✅ QR code reference lookup still works (NULL-safe query)
- ✅ Cache invalidation on admin edits (write actions flush tags; reads respect TTL)

## Verdict

**QA PASSED**

All acceptance criteria verified with passing tests. Edge cases probed. No regressions. Status → `qa-passed`.
