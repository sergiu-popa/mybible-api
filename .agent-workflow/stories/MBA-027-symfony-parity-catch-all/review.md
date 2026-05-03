# Review: MBA-027-symfony-parity-catch-all

## Verdict

**APPROVE** — second-pass review after the engineer's W1-W6 remediation
commit (`710f614`). All previously flagged warnings are addressed. No
critical findings; no unchecked warnings. Status moves to `qa-ready`.

The story shipped 220+ changed files across seven Symfony-parity domains
(Devotional types, Mobile versions, Collections, QR codes, Olympiad
attempts, Notes/Favorites colour, News detail). Migrations are guarded
for re-runs, the domain/Action/Resource split is consistent, route-model
binding correctly scopes the Olympiad attempt resource to the
authenticated user, the backwards-compat windows for `devotionals.type`
and `qr_codes.url` are honoured, and tests cover all new endpoints
including the previously missing admin-attempts list. Full suite green
(1270 passed, per commit `e1da560`).

---

## Previous-pass remediation verification

- [x] **W1. `SubmitOlympiadAttemptAnswersAction` — `created_at` clobber on
  re-submit.** Fixed in `app/Domain/Olympiad/Actions/SubmitOlympiadAttemptAnswersAction.php:57-81`:
  the upsert is split into existence-check + UPDATE-or-INSERT branches,
  with `created_at` only set on INSERT. Regression test added in
  `tests/Feature/Api/V1/Olympiad/Attempts/SubmitOlympiadAttemptAnswersTest.php`
  (`test_resubmitting_an_answer_preserves_created_at`).

- [x] **W2. Collections actions skipped the cache layer.** Both
  `ListCollectionsAction.php:13-39` and `ShowCollectionAction.php:12-34`
  now route through `CachedRead::read()` with 1h TTL and shared
  `CollectionsCacheKeys::tagsForTopicsList()`. Write-side actions
  (`CreateCollectionAction`, `UpdateCollectionAction`,
  `DeleteCollectionAction`) flush the same tag, so admin edits invalidate
  both the list and the per-slug detail cache.

- [x] **W3. `MobileVersionResource` shape-switching coupling.** Resource
  split into a public `MobileVersionResource` (array payload, locked
  5-key mobile-client shape) and a new `AdminMobileVersionResource`
  (model row, full DB shape with `released_at`, `release_notes`,
  `store_url`). All admin Mobile controllers updated to use the new
  resource (`Create`, `List`, `Update`).

- [x] **W4. `MobileVersionsRepository` memo lying about scope.** Bound
  as a singleton in `AppServiceProvider::register()` (line 67), so the
  in-process `$memo` is now actually shared across collaborators
  (`ShowMobileVersionAction`, `ShowAppBootstrapAction`,
  `Create/Update/DeleteMobileVersionAction`) within one request. Comment
  documents the why.

- [x] **W5. `UpdateMobileVersionRequest` allowed silent UNIQUE
  violations.** `app/Http/Requests/Admin/Mobile/UpdateMobileVersionRequest.php:23-53`
  now adds `Rule::unique('mobile_versions', 'platform')` and
  `Rule::unique('mobile_versions', 'kind')` scoped by the *effective*
  tuple (request value falling through to the existing row), with
  `ignore($ignoreId)`. Test added (`AdminMobileVersionsTest`) confirms a
  collision returns 422 instead of leaking a 500.

- [x] **W6. Missing `AdminListOlympiadAttemptsTest`.** New file
  `tests/Feature/Api/V1/Admin/Olympiad/AdminListOlympiadAttemptsTest.php`
  covers the 401/403 happy paths and the `user_id` /
  `language+book+chapters` filter combinations.

## Suggestions (non-blocking)

- **`OlympiadAttemptAnswer::$primaryKey = 'attempt_id'` footgun
  remains.** `app/Domain/Olympiad/Models/OlympiadAttemptAnswer.php:33` —
  carried over from the previous review's suggestion list. The current
  action correctly uses query-builder `updateOrInsert`, but a future
  engineer reaching for `OlympiadAttemptAnswer::find($attemptId)` would
  silently get the first row matching `attempt_id`. Documented as a
  suggestion only; revisit if anyone adds an Eloquent-find path.

- **`SubmitOlympiadAttemptAnswersAction` reads `completed_at` outside
  the transaction.** `app/Domain/Olympiad/Actions/SubmitOlympiadAttemptAnswersAction.php:23-26`
  — the read happens before the `DB::transaction` body. A concurrent
  finish call could let answers slip in after a finish. Risk is low
  (single user, throttled `per-user`), but a `lockForUpdate` on the
  attempt inside the transaction would close the race if/when it
  matters. Carrying over from prior review.

- **`UpdateCollectionTopicAction` cannot move a topic between
  collections.** `collection_id` is fillable on the model but the
  Action/DTO ignores it. Out of plan scope; admin MB-015 will likely
  need this — flag for the next story.

- **`Route::middleware('cache.headers:public;max_age=86400;etag')` on
  `GET /qr-codes`.** `routes/api.php:625-630` — 24h CDN cache on a
  reference-keyed lookup. Admin destination edits won't propagate for a
  full day. Consider matching the rest of the public surface (1h) or
  acknowledging the deviation in a doc-comment. Carried over.

- **Public `OlympiadAnswerResource.is_correct` leak.** Pre-existing,
  intentional per the comment in `ShowOlympiadThemeController` (clients
  used to score client-side). Now that server-side scoring lives on
  attempts, the residual leak deserves a follow-up story to gate
  `is_correct` and migrate clients off client-side scoring. Out of
  MBA-027 scope.

## Scope-Confirmation Notes

These judgment calls in `plan.md` were honoured correctly:

- §41 keeps `QrCodeListItemResource` as a duplicate of `QrCodeResource`
  rather than extracting a base — engineer's call, fine.
- §39 `RecordQrCodeScanAction` dispatches the event with no listener
  (MBA-030 lands the analytics subscriber). `Event::fake` proves the
  contract in `RecordQrCodeScanTest`.
- §27 attempt → question-set "lock" is implemented as
  `(book, chapters_label, language)` membership via `matchingTheme()`,
  not a join table. Plan documents this trade-off.
- §10 `MobileVersionResource` keeps the existing config-shape keys for
  the public endpoint — verified in `tests/Feature/Api/V1/Mobile/ShowMobileVersionTest.php`.
- News `resolveRouteBinding` correctly applies `published()`.
- `OlympiadAttempt::resolveRouteBinding` correctly scopes to the
  authenticated user.
- `qr_codes.destination` ↔ `qr_codes.url` deprecation window honoured;
  admin writes mirror, reads serve `destination ?? url`.
