# Code Review — MBA-001

## Summary
The reading-plan catalog slice lands cleanly. Domain layout, models, query
builder, language resolver, form requests, resources, controllers, factories,
seeder, and feature + unit tests all align with architecture.md, follow Beyond
CRUD conventions, and honour the project's JSON-only override rules (no
Blade/Livewire bleed). `make test` (74/74), `make stan`, and Pint are all
green. One Warning to resolve around an ambiguity between AC 7 and the form
request validation rule; otherwise findings are tightening suggestions.

## Findings

### Critical (must fix before merge)
- _None._

### Warning (should fix)
- [ ] `app/Http/Requests/ReadingPlans/ListReadingPlansRequest.php:27`,
  `app/Http/Requests/ReadingPlans/ShowReadingPlanRequest.php:23` — the
  `language` rule `in:en,ro,hu` returns a **422** for unsupported values
  (e.g. `?language=fr`). This contradicts story AC 7: *"other values fall
  through to the `en` fallback."* The architecture doc specifies the `in:`
  rule, but the architecture itself deviates from AC 7 on this point, and no
  test pins either behavior. **Fix:** pick one and align. Either (a) drop the
  `in:` rule and let `Language::fromRequest()` silently fall back — which is
  what `Language::fromRequest()` and `LanguageTest` already support — or
  (b) update story AC 7 to say unsupported languages return 422. Add a test
  (`test_it_falls_back_on_unknown_language` or `test_it_rejects_unknown_language`)
  for whichever path is chosen.

### Suggestion (nice to have)
- [ ] `routes/api.php:13` — `resolve-language` is applied to the entire `v1`
  group, including auth routes that never read the language. Scope it to the
  reading-plans group (or any future i18n routes) to avoid coupling unrelated
  routes to a reading-plans-prefixed container binding.
- [ ] `routes/api.php:26` — architecture.md specifies `{plan:slug}` route-model
  binding, but the route uses `{slug}` and the controller resolves manually
  via `where('slug', $slug)`. Functionally equivalent, but either (a) switch
  to implicit binding (`Route::get('{plan:slug}', ShowReadingPlanController::class)`)
  and let the query builder's `published()` scope still run by explicit
  chaining in the controller, or (b) update architecture.md so future readers
  don't expect binding. Current approach also returns a manually thrown
  `ModelNotFoundException` which the exception handler already maps to the
  JSON 404 envelope — works, but is a little noisier than needed.
- [ ] `app/Http/Resources/ReadingPlans/ReadingPlanResource.php:25`,
  `app/Http/Resources/ReadingPlans/ReadingPlanDayFragmentResource.php:46` —
  resources depend on `app(ResolveRequestLanguage::CONTAINER_KEY)` being
  bound by middleware. If a future route forgets to apply the middleware, the
  resource hard-fails with a `BindingResolutionException`. Safer options:
  default the container lookup (`app()->bound(...) ? ... : Language::En`),
  or read the language from the form request once in the controller and
  thread it through the resource via `->additional(['language' => $lang])`
  / a constructor argument. The current pattern works today but is fragile.
- [ ] Architecture.md testing table lists `ReadingPlanResourceTest`,
  `ReadingPlanDayFragmentResourceTest`, `ListReadingPlansRequestTest`,
  `ShowReadingPlanRequestTest` — none of these exist. The feature tests cover
  the behavior end-to-end, so functionally nothing is missing, but explicit
  unit tests would give faster feedback on resource/request edge cases and
  match what was planned. Either add them or prune the architecture table.
- [ ] `app/Http/Resources/ReadingPlans/ReadingPlanResource.php:34` — the
  catalog is already filtered to `Published` by the query builder, so every
  returned plan has `status = 'published'`. Exposing it adds no information
  for a public client and would need churn if the status model grows (e.g.
  `Archived`). Consider dropping the field from the public resource; the
  show-endpoint contract can still expose it later if/when needed.
- [ ] `tests/Feature/Api/V1/ReadingPlans/ListReadingPlansTest.php:20-24`,
  `tests/Feature/Api/V1/ReadingPlans/ShowReadingPlanTest.php:20-25` — the
  api-key config setup is duplicated. Extract into a small shared trait
  (e.g. `WithApiKeyClient`) to DRY and make future key-related tests
  consistent.

## Checklist
- [x] All acceptance criteria from story.md are met *(AC 7 edge case — see Warning)*
- [x] Architecture matches architecture.md *(two minor deviations in Suggestions)*
- [x] All tasks in tasks.md are completed
- [x] Tests exist for all new code
- [x] Tests pass — 74 passed, 199 assertions
- [x] No security issues found
- [x] No performance issues found
- [x] Code style matches guidelines — strict types, final classes, explicit return types, no `else`
- [x] Pint + PHPStan green
- [x] JSON-only API conventions honored (no Blade/Livewire leakage)

## Verdict
APPROVE
