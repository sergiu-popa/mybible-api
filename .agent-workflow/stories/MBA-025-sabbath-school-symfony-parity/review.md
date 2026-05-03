# Review: MBA-025-sabbath-school-symfony-parity

**Verdict:** APPROVE

Re-review after the engineer addressed every Critical and Warning from the
prior pass. Spot-checked the schema migrations, the highlight + favorite
toggle paths, the Trimester / Lesson / Segment-Content admin surface, the
new partial-DTO Update flow, and the migration + unit test coverage. No
new blockers surfaced.

---

## Resolved findings (from previous pass)

- [x] **Critical: Highlight unique now includes `deleted_at`.**
  `database/migrations/2026_05_03_002005_evolve_sabbath_school_highlights_for_offsets.php:60-65`
  unique columns are `(user_id, segment_content_id, start_position,
  end_position, deleted_at)`. The toggle-off-then-on regression is covered
  by `tests/Unit/Domain/SabbathSchool/Actions/ToggleSabbathSchoolHighlightActionTest.php:68-90`
  (`test_toggling_off_then_on_recreates_the_highlight`) which calls the
  action three times and asserts a single live row + a single trashed row.

- [x] **Critical: Migration tests for §68 and §69 added.**
  `tests/Feature/Domain/SabbathSchool/Migrations/EvolveSabbathSchoolSegmentsForForDateTest.php`
  covers `for_date = lesson.date_from + day days` backfill, NULL `day` no-op,
  and idempotent rerun. `tests/Feature/Domain/SabbathSchool/Migrations/RelaxFavoritesSegmentUniquenessTest.php`
  covers sentinel `0 → NULL` rewrite, NULL/non-NULL coexistence on
  `(user, lesson)`, and the duplicate-NULL collision via the functional
  unique.

- [x] **Warning: Answer upsert returns 422 for non-question content.**
  `app/Http/Requests/SabbathSchool/UpsertSabbathSchoolAnswerRequest.php:50-66`
  moved the type guard into `withValidator()->after(...)`, leaving
  `authorize()` to gate only on user + published lesson. Feature test
  `tests/Feature/Api/V1/SabbathSchool/SabbathSchoolAnswerTest.php` updated
  accordingly.

- [x] **Warning: `ShowTrimesterAction` scopes by language.**
  `app/Domain/SabbathSchool/Actions/ShowTrimesterAction.php:27-30` now
  `->forLanguage($language)->withLessons()->findOrFail($trimesterId)`,
  closing the cross-language cache pollution risk.

- [x] **Warning: Highlight route binding fails closed.**
  `app/Domain/SabbathSchool/Models/SabbathSchoolHighlight.php:85-99`
  early-returns `null` when the request user is null, then unconditionally
  applies `where('user_id', $userId)`. The `when()` short-circuit is gone.

- [x] **Warning: Update Actions take typed partial DTOs.**
  New `UpdateTrimesterData`, `UpdateLessonData`, `UpdateSegmentContentData`
  ship as `final readonly` classes with `from(array): self` factories that
  track which keys were present, plus a `toArray()` that emits only those
  keys via `array_intersect_key(..., array_flip($this->present))`. The
  Update Actions (`UpdateTrimesterAction.php:14-22`,
  `UpdateLessonAction.php:14-22`, `UpdateSegmentContentAction.php:14-24`)
  now type-hint on these and `fill($data->toArray())`. Partial-PATCH
  semantics preserved without leaving `$guarded = []` exposed to raw
  arrays.

- [x] **Warning: `SabbathSchoolHighlightFactory::definition()` materialises one segment.**
  `database/factories/SabbathSchoolHighlightFactory.php:23-56` builds the
  `SabbathSchoolSegmentContent` first, then resolves
  `sabbath_school_segment_id` via a closure that reads the new content's
  `segment_id`. Default-state highlights now have aligned segment IDs.

## Suggestions

- Admin list controllers (`ListAdminLessonsController`,
  `ListAdminTrimestersController`, `ListSegmentContentsController`) still
  build queries inline. The CLAUDE.md "Controllers contain no business
  logic" rule is mildly bent here, but it matches the
  `ListSabbathSchoolLessonsController` precedent on the public side and
  was acknowledged in the prior pass — flagging again only as a low-priority
  cleanup target. Acknowledged: in line with existing precedent across the
  codebase, not a regression introduced by this story.
- `ListSabbathSchoolTrimestersController.php:24-25` and the Show variant
  set `Cache-Control` manually rather than relying on
  `cache.headers:public;max_age=3600;etag` middleware. The middleware would
  add an ETag/304 short-circuit; current behaviour matches the lessons
  precedent. Out-of-scope for this story.
- `UpdateLessonData::publishedAt` round-trips through
  `CarbonImmutable::parse(...)->toDateTimeString()` — fine for
  `Y-m-d H:i:s` payloads, but if a future request body sends
  `'published_at' => null` (explicit unpublish via PATCH), the DTO drops
  it from `toArray()` because the closure short-circuits on `=== null`.
  Today the FormRequest probably forbids `null`, but the DTO is more
  restrictive than the validator implies. Worth a follow-up if the
  Lesson admin ever needs to allow re-drafting via PATCH.
- `UpdateSegmentContentAction.php:18` calls
  `$content->segment()->value('sabbath_school_lesson_id')` to resolve the
  parent lesson for cache invalidation. Cheaper to load the segment once
  via relation if the controller already eager-loads, otherwise this is
  a single extra column-only query — minor.

---

## Plan deviations acknowledged

- All deviations noted in the previous pass remain valid (passage column
  kept nullable, segment_content_id FK reintroduction deferred to MBA-032,
  functional COALESCE unique on favourites, retired questions reorder
  endpoint).
- Partial-PATCH DTOs (`UpdateTrimesterData::$present` array of supplied
  keys) is a small extension beyond plan §31 (which described full DTOs)
  — the engineer added the partial variant to address the previous
  warning while preserving PATCH semantics. Reasonable choice; documented
  inline in each DTO's PHPDoc.
