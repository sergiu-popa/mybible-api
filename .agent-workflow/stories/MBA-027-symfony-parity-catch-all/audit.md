# Audit: MBA-027-symfony-parity-catch-all

## Verdict

**PASS** — three Warnings fixed (one functional contract bug, two API-design parity gaps with W5). Five Suggestions accounted for (carried over from review.md, all justified or deferred). Full suite green: **1277 passed**, 0 failures. Lint + stan clean.

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| A1 | Public `GET /olympiad/themes/{book}/{chapters}` returned `uuid: null` for every question and answer because `FetchOlympiadThemeQuestionsAction::loadThemeQuestions()` cached only `id/question/explanation` (and `id/text/is_correct` per answer). The Resources (`OlympiadQuestionResource`, `OlympiadAnswerResource`) read `$this->uuid` from a `forceFill`-rebuilt model, so UUIDs were silently dropped after the first cache hit. This breaks AC §27 — clients cannot drive `POST /olympiad/attempts/{attempt}/answers` (which keys on `question_uuid` + `selected_answer_uuid`) without the public theme endpoint exposing answer UUIDs. | `app/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsAction.php:32-65, :80-117` | Warning | Fixed | Cached payload now carries `uuid`/`verse`/`chapter`/`is_reviewed` per question and `uuid` per answer; reconstituted models forceFill the same fields. New `ShowOlympiadThemeControllerTest` assertions verify question + answer UUIDs are non-empty strings in the response. |
| A2 | `POST /admin/mobile-versions` leaked the DB UNIQUE `(platform, kind)` violation as a 500 (the test under `AdminMobileVersionsTest::test_create_rejects_duplicate_platform_kind` literally `assertStatus(500)`-ed). Update side already returns 422 via Rule::unique (W5 fix); Create lagged behind. | `app/Http/Requests/Admin/Mobile/CreateMobileVersionRequest.php` | Warning | Fixed | Mirrored W5: added `Rule::unique('mobile_versions', 'kind')->where(...)` scoped by the request's `platform`. Test renamed to `_with_422`, now asserts `assertUnprocessable()->assertJsonValidationErrors(['kind'])`. |
| A3 | `POST /admin/qr-codes` had the same 500-leak on UNIQUE `(place, source)` collision (`AdminQrCodesTest::test_create_unique_place_source_collision_returns_422` asserted 500 with a self-incriminating comment "No app-level uniqueness check; falls back to constraint violation"). | `app/Http/Requests/Admin/QrCode/CreateQrCodeRequest.php` | Warning | Fixed | Added `Rule::unique('qr_codes', 'source')->where(place=...)`. Test now asserts 422 + `errors.source`. |
| S1 | `SubmitOlympiadAttemptAnswersAction` reads `$attempt->completed_at` *outside* the `DB::transaction` body. A concurrent `finish` call could let answers slip in after a finish. | `app/Domain/Olympiad/Actions/SubmitOlympiadAttemptAnswersAction.php:23-26` | Suggestion | Skipped | Carried over from `review.md`. Routes are gated by `auth:sanctum` + `throttle:per-user` (single-user serial requests). Adding `lockForUpdate` would prevent the theoretical race but trades a per-request row lock for a vanishingly rare edge case. Revisit if/when contention is observed. |
| S2 | `OlympiadAttemptAnswer::$primaryKey = 'attempt_id'` foot-gun — Eloquent will treat `OlympiadAttemptAnswer::find($attemptId)` as a single-row lookup keyed on `attempt_id` and return the first matching answer rather than 422-ing. | `app/Domain/Olympiad/Models/OlympiadAttemptAnswer.php:33` | Suggestion | Skipped | Carried over from `review.md`. The current Action uses query-builder `updateOrInsert`, never `Model::find()`, so no caller is exposed. The `$primaryKeyComposite` field documents the real key. Revisit if a future Eloquent-find path appears. |
| S3 | Public `GET /qr-codes` is wrapped in `cache.headers:public;max_age=86400;etag` (24h CDN cache) — admin destination edits won't propagate for a full day. | `routes/api.php:626-632` | Suggestion | Deferred | Carried over from `review.md`. Tracked there as a known deviation from the 1h public-read norm. Mobile clients refresh aggressively on app-open, so the user-visible impact is bounded. Decision to keep 24h is product-side. |
| S4 | `OlympiadAnswerResource.is_correct` leaks publicly on the theme endpoint — pre-MBA-027 surface, used historically for client-side scoring before attempts persistence existed. | `app/Http/Resources/Olympiad/OlympiadAnswerResource.php:25` | Suggestion | Deferred | Carried over from `review.md`. Migration plan: gate `is_correct` and migrate clients off client-side scoring once mobile/web ship the attempt-submission UX. Out of MBA-027 scope; needs a dedicated story. |
| S5 | `UpdateCollectionTopicAction` ignores `collection_id` (cannot move a topic between collections). | `app/Domain/Collections/Actions/UpdateCollectionTopicAction.php` | Suggestion | Deferred | Carried over from `review.md`. Admin MB-015 will likely need the move operation; flagged for the next admin story. |

## Tests touched

- `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php` — extended `test_it_returns_questions_with_answers_and_seed_meta` to assert UUIDs are present and non-empty for every question and answer.
- `tests/Feature/Api/V1/Admin/Mobile/AdminMobileVersionsTest.php` — renamed `test_create_rejects_duplicate_platform_kind` → `_with_422` and asserts `assertUnprocessable + assertJsonValidationErrors(['kind'])`.
- `tests/Feature/Api/V1/Admin/QrCode/AdminQrCodesTest.php` — `test_create_unique_place_source_collision_returns_422` now actually asserts 422 + `errors.source`.

## Gate

- `make lint-fix`: clean (1175 files PASS).
- `make stan`: clean (1151 files, no errors).
- `make test-api`: **1277 passed** (4775 assertions, 42.5s).

Status moves to `done`.
