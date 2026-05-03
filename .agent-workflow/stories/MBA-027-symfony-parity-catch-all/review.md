# Review: MBA-027-symfony-parity-catch-all

## Verdict

**REQUEST CHANGES** — one warning (W1) flags a small data-integrity bug in
the Olympiad answer upsert path. The remaining warnings are
plan-deviation / brittleness concerns the engineer should action or
acknowledge before APPROVE. No critical issues; status stays
`in-review`.

The story is ambitious (220 changed files across 7 domains) and the
overall shape is solid: migrations are guarded for re-runs, the
domain/Action/Resource split is consistent, route-model binding correctly
scopes the Olympiad attempt resource to the authenticated user, and the
backwards-compat windows for `devotionals.type` and `qr_codes.url` are
honoured. Tests cover the bulk of the new endpoints. Findings are
mostly small, but W1 affects on-disk state.

---

## Warnings

- [x] **W1. `SubmitOlympiadAttemptAnswersAction` clobbers `created_at` on
  every idempotent re-submit.** `app/Domain/Olympiad/Actions/SubmitOlympiadAttemptAnswersAction.php:57-65`
  passes `created_at => now()` inside the `$values` array of
  `updateOrInsert($attributes, $values)`. Laravel's `updateOrInsert`
  re-uses `$values` for *both* the INSERT and the UPDATE branch, so on
  the UPDATE branch the original `created_at` of the first answer is
  overwritten. AC §27 says answer submission is *idempotent*; the
  primary-key row is preserved, but the audit trail of "when did the
  user first answer this question" is silently destroyed each
  resubmit. Fix: split the call so only the UPDATE path omits
  `created_at`, e.g.

  ```php
  $now = now();
  $existing = OlympiadAttemptAnswer::query()
      ->where('attempt_id', $attempt->id)
      ->where('olympiad_question_id', $questionId)
      ->exists();

  if ($existing) {
      OlympiadAttemptAnswer::query()
          ->where('attempt_id', $attempt->id)
          ->where('olympiad_question_id', $questionId)
          ->update([
              'selected_answer_id' => $selectedAnswerId,
              'is_correct' => $isCorrect,
              'updated_at' => $now,
          ]);
  } else {
      OlympiadAttemptAnswer::query()->insert([
          'attempt_id' => $attempt->id,
          'olympiad_question_id' => $questionId,
          'selected_answer_id' => $selectedAnswerId,
          'is_correct' => $isCorrect,
          'created_at' => $now,
          'updated_at' => $now,
      ]);
  }
  ```

  Add a regression test that submits twice and asserts `created_at`
  does not advance on the second call.

- [x] **W2. `ListCollectionsAction` and `ShowCollectionAction` skip the
  caching layer the plan requires.** Plan §28 says "both cached 1h
  with `CollectionsCacheKeys` extensions"; the implementations in
  `app/Domain/Collections/Actions/ListCollectionsAction.php:14-23` and
  `app/Domain/Collections/Actions/ShowCollectionAction.php:11-17`
  paginate / load directly without going through `CachedRead`.
  `CollectionsCacheKeys` was extended (`app/Domain/Collections/Support/CollectionsCacheKeys.php`)
  but is unused by these new endpoints. Public-read endpoints in this
  repo (devotionals, news, hymnal-books, etc.) all flow through
  `CachedRead` for a reason — at scale, every cold read for the
  collections list will hit the DB. Either wire the cache through, or
  acknowledge the deviation (e.g. "list size is small, route already
  has cache-control headers, deferred to MBA-031 ETL").

- [x] **W3. `MobileVersionResource` switches output shape based on
  `$this->resource instanceof MobileVersion`.** `app/Http/Resources/Mobile/MobileVersionResource.php:26-47`
  emits a 7-key admin shape when fed a model and a 5-key public shape
  when fed an array. This works but couples two contracts inside one
  Resource and means the admin paginator (returning a model) and the
  public endpoint (returning an array) silently produce different
  JSON keys. Future-you reading admin JSON can't tell from the
  Resource alone what to expect. Suggested fix: split into
  `AdminMobileVersionResource` and `PublicMobileVersionResource`, each
  with one toArray and one input type — same precedent as the public/admin
  Resource split in MBA-024.

- [x] **W4. `MobileVersionsRepository` is not bound as a singleton, but
  uses an in-process `$memo` map.** `app/Domain/Mobile/Support/MobileVersionsRepository.php:14`
  declares `$memo` and consults it before going to `Cache::remember`.
  Because it isn't `bind`-as-singleton in any service provider, every
  resolve gets a fresh repo, so `$memo` is dead within a single
  request's lifetime once two collaborators each inject it (e.g.
  `ShowMobileVersionAction` + `ShowAppBootstrapAction`). The `Cache`
  layer still does its job — this is a perf microopt, not a
  correctness issue — but the memo as written is lying about what it
  buys. Either make the repository a singleton in
  `AppServiceProvider`, or remove `$memo` entirely.

- [x] **W5. `UpdateMobileVersionRequest` allows changing
  `(platform, kind)` without re-checking the UNIQUE.**
  `app/Http/Requests/Admin/Mobile/UpdateMobileVersionRequest.php` —
  if an admin PATCHes `platform` or `kind` to a tuple already taken
  (e.g. swapping the existing `(ios, latest)` row to `(android, latest)`
  when an android-latest row already exists), the request validates,
  the action runs, MySQL returns `Integrity constraint violation`, and
  the user gets a generic 500. Add `Rule::unique('mobile_versions')->where(…)
  ->ignore($this->route('version')->id)` on `platform` and `kind`
  when either is changing, mirroring how `UpdateDevotionalTypeRequest`
  handles its UNIQUE.

- [x] **W6. Missing feature test for `ListAdminOlympiadAttemptsController`.**
  Plan task §62 calls for `AdminListOlympiadAttemptsTest` (auth + filters);
  `tests/Feature/Api/V1/Admin/Olympiad/` only contains
  `ReorderOlympiadQuestionsTest.php`. The endpoint is super-admin
  gated and does cross-user reads — it's exactly the kind of route
  that needs a 401/403/200 happy path test. Add one before APPROVE.

## Suggestions

- **`UpdateNoteRequest::toData` has dead `colorRaw === ''` branch.**
  `app/Http/Requests/Notes/UpdateNoteRequest.php:39-42` — empty string
  cannot reach `toData` because `HexColor` validation rejects it. The
  branch reads as defensive code that masks a different intent. Drop
  it (or document why it stays). Same observation in
  `UpdateFavoriteRequest`.

- **`StartOlympiadAttemptAction` happily creates `total = 0` attempts.**
  `app/Domain/Olympiad/Actions/StartOlympiadAttemptAction.php:19-33` —
  if a user requests a theme that has no questions yet (admin in
  progress), an attempt row is persisted with `total = 0`. The user
  can never submit anything against it (theme-mismatch on every
  question). Cleanest behaviour: the controller 422s with a
  "no questions for this theme" message before persisting. Otherwise
  acknowledge: "ok, the row is harmless and `LIST` shows it as
  finished-with-zero".

- **`Route::patch('{version}', UpdateMobileVersionController::class)`
  uses an ambiguous parameter name.** `routes/api.php:501-502` —
  `{version}` reads like the version *string* in the URL but it's the
  numeric model id. Rename to `{mobileVersion}` for parity with
  `{collection}` / `{type}` / `{qr}` further down the file.

- **`Route::middleware(['…', 'cache.headers:public;max_age=86400;etag'])`
  on `GET /qr-codes`.** `routes/api.php:625-630` caches the
  reference-keyed QR lookup for 24 h. Admin edits to a QR's destination
  won't reach end-users for a full day. Either drop to 1 h to match
  the rest of the public surface, or invalidate via a tag in
  `UpdateQrCodeAction` (it already flushes `QrCodeCacheKeys::tagsForQr`,
  but `cache.headers` is a CDN edge directive, not a tagged cache).
  Consider whether the 24h was intentional (the QR's `place`/`source`
  identity is stable and only `destination` changes when admin updates).

- **`UpdateCollectionTopicAction` cannot move a topic to a different
  collection.** `app/Domain/Collections/Actions/UpdateCollectionTopicAction.php`
  — `collection_id` is fillable on the model but the DTO and Action
  ignore it. Out-of-plan, so not blocking, but admin MB-015 will
  almost certainly need this — flag for the next story.

- **`OlympiadAttemptAnswer::$primaryKey = 'attempt_id'` is a footgun.**
  `app/Domain/Olympiad/Models/OlympiadAttemptAnswer.php:33` — Eloquent
  doesn't support composite primary keys; `find()` and any future
  `find()`-based code paths would lookup by `attempt_id` only and
  return wrong rows. The action correctly works around this via
  query-builder `updateOrInsert`, but a future engineer using
  `OlympiadAttemptAnswer::find(...)` without reading the model class
  will silently get a wrong answer. Either add a generated
  surrogate `id` column, or wrap the model with a
  `protected static function boot()` that rejects `find()` /
  `findOrFail` / `whereKey()` calls.

- **`SubmitOlympiadAttemptAnswersAction` reads `completed_at` outside
  the transaction.** `app/Domain/Olympiad/Actions/SubmitOlympiadAttemptAnswersAction.php:23-26`
  — a concurrent finish call between the read and the
  `DB::transaction` body could let answers slip in after a finish.
  Risk is low (single user, throttled) but a `DB::transaction`
  + `lockForUpdate` on the attempt would close the gap if the team
  ever cares.

- **Public read of `OlympiadAnswerResource` exposes `is_correct`** —
  pre-existing, intentional per the comment in
  `ShowOlympiadThemeController` (client-side scoring). Now that
  server-side scoring lives on attempts, MBA-027 doesn't change the
  public contract, but the residual leak is worth a follow-up story
  to gate `is_correct` on admin auth and migrate clients to the
  attempt-API for scoring.

- **`OlympiadAttemptResource` includes `language` even though the
  user-history query is filterable by language.** Per Code-Reviewer
  rule "flag any response field whose value is constant under the
  query scope" — but this scope is *the user*, not language, so a
  user querying their full history needs `language` to disambiguate.
  Not constant. Keep.

## Scope-Confirmation Notes

Several judgment calls in `plan.md` were honoured correctly and don't
need to be re-litigated:

- §41 keeps `QrCodeListItemResource` as a duplicate of `QrCodeResource`
  rather than extracting a base — engineer's call, fine.
- §39 `RecordQrCodeScanAction` dispatches the event but no listener
  consumes it (MBA-030 lands the analytics subscriber). `Event::fake`
  in `RecordQrCodeScanTest` proves the contract.
- §27 attempt → question-set "lock" is implemented as
  `(book, chapters_label, language)` membership via
  `matchingTheme()`, not a join table. Plan documents this trade-off.
- §10 `MobileVersionResource` keeps the existing config-shape keys for
  the public endpoint — verified in `tests/Feature/Api/V1/Mobile/ShowMobileVersionTest.php`.
- News `resolveRouteBinding` correctly applies `published()`.
- `OlympiadAttempt::resolveRouteBinding` correctly scopes to the
  authenticated user — verified by
  `SubmitOlympiadAttemptAnswersTest::test_it_404s_for_other_users_attempt`.
