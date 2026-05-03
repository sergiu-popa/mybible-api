# Code Review — MBA-026 Resource Books & Polymorphic Downloads

Reviewed commit: `8ada679 [MBA-026] Resource books + polymorphic resource downloads`,
plus the in-tree fixes for W1–W4 staged on top.

## Summary

The implementation faithfully follows the architectural plan. Schema migrations
are idempotent and create-or-evolve correctly; the polymorphic
`resource_downloads` shape with morphMap aliases is sound; admin and public
HTTP surfaces are wired with the correct middleware (`api-key-or-sanctum` for
public/anon, `auth:sanctum + super-admin` for admin); cache invalidation runs
on the relevant write actions; tests cover happy paths and the contracted
404/422/429/400 cases.

Re-review pass: W1–W4 from the prior round have been addressed in
`SummariseResourceDownloadsAction`, `ReorderResourceBookChaptersAction`, and
`UpdateResourceBookRequest`, with new feature tests covering the previously
uncovered scenarios (partial chapter reorder, inclusive 7-day window,
group_by week/month → 400, published_at preserved on PATCH). Targeted suites
(`ResourceBook`, `ResourceDownload`) are green: 51 tests / 282 assertions.

## Critical

_None._

## Warnings

- [x] **W1 — `ReorderResourceBookChaptersAction` collides under partial reorder.**
  `app/Domain/EducationalResources/Actions/ReorderResourceBookChaptersAction.php:36-53`
  uses a two-pass shift with `offset = count($ids) + 1`. The pass-1 target
  range is `[count+1, 2*count]`. If the request omits any chapter and an
  excluded chapter happens to live in that range, pass 1 collides with the
  unique `(resource_book_id, position)` index and the transaction throws.
  Concretely: book has chapters at positions `1,2,3,4`; client sends
  `ids=[1,2,3]` (full reorder of three of them, leaving chapter 4 at pos 4);
  offset = 4; pass 1 sets chapter 1 → position 4 → unique-index collision.
  The plan and the test only exercise the "all chapters in the request"
  shape, so this is a latent bug only the admin client can trigger, but the
  fix is small.
  **Suggested fix:** compute offset from the actual maximum, e.g.
  `$offset = (int) ResourceBookChapter::query()->where('resource_book_id', $book->id)->max('position') + 1;`
  so pass 1 always targets a range above every existing position. Alternatively,
  add a validation rule asserting `count($ids) === $book->chapters()->count()`
  if "must include all chapters" is the intended contract.
  **Resolution:** fix-as-suggested adopted at
  `ReorderResourceBookChaptersAction.php:41-43` (`max('position') + 1`).
  Coverage: `AdminResourceBookChaptersTest::test_reorder_handles_partial_list_without_collision`
  exercises the exact W1 scenario (4 chapters, reorder 3 of them, leaving
  chapter 4 untouched at position 4) and passes.

- [x] **W2 — `SummariseResourceDownloadsAction` ignores `group_by`.**
  `app/Domain/Analytics/Actions/SummariseResourceDownloadsAction.php:38-46`
  always groups by `DATE(created_at)` even when the request specifies
  `group_by=week` or `group_by=month`. The validator
  (`ShowResourceDownloadsSummaryRequest::rules` line 29) accepts those
  values, the controller echoes them back into `meta.group_by`
  (`ShowResourceDownloadsSummaryController.php:26`), but the rows returned
  are always day-bucketed. With `range > 7d` the action throws (deferred to
  MBA-030), so for the in-scope `≤7d` window only `day` grouping is
  meaningful — the fact that `week`/`month` validate but silently produce
  day rows is a misleading API contract.
  **Suggested fix:** either reject `group_by` in `{week, month}` here with
  the same `BadRequestHttpException`-style "requires MBA-030 rollups"
  message (so MBA-030 owns those branches), or compute the SQL grouping
  expression based on `$query->groupBy` (`DATE(...)`, `YEARWEEK(...)`,
  `DATE_FORMAT(..., '%Y-%m')`) so the parameter actually does something.
  **Resolution:** rejection path adopted at
  `SummariseResourceDownloadsAction.php:18-22` — non-`day` groupings throw
  the same `"long-range download summary requires MBA-030 rollups"`
  message. Coverage:
  `AdminResourceDownloadsSummaryTest::test_week_grouping_returns_400_until_mba_030`
  and `…::test_month_grouping_returns_400_until_mba_030`.

- [x] **W3 — 7-day boundary check is off by sub-day fraction.**
  `app/Domain/Analytics/Actions/SummariseResourceDownloadsAction.php:18-24`
  uses `$query->from->diffInDays($query->to) + 1`. The DTO is built from
  `startOfDay`/`endOfDay`, so a same-week 7-day request (Mon 00:00 → Sun
  23:59:59.999999) yields `diffInDays ≈ 6.999988` → `+1 = 7.999988 > 7` →
  `BadRequestHttpException`. A `≤7-day` window that the plan and AC §22
  treat as in-scope is rejected at the boundary.
  **Suggested fix:** compare on calendar days, not float days:
  `$rangeDays = (int) $query->from->startOfDay()->diffInDays($query->to->startOfDay()) + 1;`
  or `$query->from->toDateString()` ↔ `$query->to->toDateString()` arithmetic.
  An explicit test at exactly 7 inclusive days would have caught this.
  **Resolution:** adopted at
  `SummariseResourceDownloadsAction.php:27` (`startOfDay()->diffInDays(startOfDay())`).
  Coverage:
  `AdminResourceDownloadsSummaryTest::test_inclusive_seven_day_window_is_accepted`
  pins `from = now()->subDays(6)`, `to = now()` and asserts 200.

- [x] **W4 — `UpdateResourceBookRequest` allows direct write to `published_at`.**
  `app/Http/Requests/Admin/EducationalResources/UpdateResourceBookRequest.php:43`
  validates `published_at` as a writable field, but
  `SetResourceBookPublicationAction` is the documented owner of that
  column (sets it on first publish, leaves it intact on unpublish so
  re-publish reuses). A super-admin sending PATCH `{published_at: null,
  is_published is unchanged}` produces a state where `is_published=true`
  but `published_at=null`, which `ResourceBookListResource::toArray()` then
  renders as `published_at: null` for a "published" book — and breaks the
  `orderByDesc('published_at')` tie-breaker in `orderedForList()`.
  **Suggested fix:** drop `published_at` from the update rules; if the
  desired UX is "operator can override the published_at date", add it as
  an explicit field to `SetResourceBookPublicationAction` instead, so the
  invariant `is_published ⇔ published_at !== null` is enforced in one
  place.
  **Resolution:** `published_at` rule removed from
  `UpdateResourceBookRequest::rules()`. Coverage:
  `AdminResourceBooksTest::test_update_ignores_published_at_field`
  asserts a PATCH with `{published_at: null}` is silently dropped and the
  original timestamp is preserved.

- [x] **W5 — Plan deviation: `Relation::morphMap` instead of `enforceMorphMap`.** — acknowledged: `app/Providers/AppServiceProvider.php:142-146` documents that Sanctum tokenables (and other unmapped polymorphs in the app) would break under `enforceMorphMap`, and falls back to `morphMap` so only the three downloadable types get aliases while existing FQCN polymorphs keep working. Justified deviation; the plan's open-question resolution did not anticipate the Sanctum constraint.

## Suggestions

- **S1 — `ShowResourceBookAction` redundant `orderBy('position')` on eager load.**
  `app/Domain/EducationalResources/Actions/ShowResourceBookAction.php:26-28`
  loads chapters with `orderBy('position')`, but
  `ResourceBook::chapters()` already declares `->orderBy('position')`
  (`ResourceBook.php:78`). The closure can just be `'chapters'`.

- **S2 — `ListResourceBooksController` hardcodes `per_page=15`.**
  `app/Http/Controllers/Api/V1/EducationalResources/ListResourceBooksController.php:20`
  inlines the page size; this is a minor inconsistency with how the cache
  key already accepts `$perPage` (`ResourceBooksCacheKeys::list`). If
  there's no plan to expose `?per_page=`, consider promoting the literal
  to a class constant on the action so the contract is grep-able.

- **S3 — `ResourceBookListResource::toArray` falls back to a runtime count
  query.** `app/Http/Resources/EducationalResources/ResourceBookListResource.php:29`
  uses `$this->chapters_count ?? $this->chapters()->count()`. Every caller
  in this story does eager-`withCount('chapters')`, so the fallback never
  fires today. Worth a comment noting "eager `withCount('chapters')` is
  required by every caller" so a future caller does not accidentally
  trigger N+1.

- **S4 — `SummariseResourceDownloadsAction` orders only by `date`.**
  `SummariseResourceDownloadsAction.php:54` uses `orderBy('date')`, leaving
  the order of rows for the same date non-deterministic across
  `downloadable_type`/`downloadable_id`/`language`. Tests don't depend on
  this, but stable output is friendlier to dashboards. Consider
  `->orderBy('date')->orderBy('downloadable_type')->orderBy('downloadable_id')->orderBy('language')`.

- **S5 — `ShowResourceBookTest` doesn't assert `Cache-Control: max-age=3600`
  on book detail.** `ShowResourceBookChapterTest::test_it_sets_short_cache_headers`
  exists for chapter (600s), and `ListResourceBooksTest::test_it_sets_public_cache_headers`
  exists for list (3600s), but the equivalent assertion for `book detail`
  (3600s) is missing. Adding it would close out AC §13's cache-header
  assertion symmetrically.

## Verdict

**APPROVE.** Status → `qa-ready`. All four blocking warnings are resolved
in code with dedicated tests; W5 remains acknowledged as a justified
plan deviation. Suggestions S1–S5 are non-blocking and at the Engineer's
discretion.
