# Improvements — MBA-001-reading-plan-catalog

## Part 1 — Code Fixes

### Issues Addressed

| # | Issue (from audit) | Severity | Status | What was done |
|---|---|---|---|---|
| 1 | Resource service-locator fragility (`ReadingPlanResource`, `ReadingPlanDayFragmentResource` pulled the resolved `Language` from the container; a future route forgetting `resolve-language` middleware would throw `BindingResolutionException` at render time) | Should Fix | Fixed | `ResolveRequestLanguage` now stores the resolved enum on `$request->attributes` (request-scoped, Laravel-idiomatic). Resources read via `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)` so a missing attribute falls back to English instead of throwing. Renamed the constant from `CONTAINER_KEY` to `ATTRIBUTE_KEY` to reflect the new storage. |
| 2 | Architecture drift on route binding & missing unit tests (arch specified `{plan:slug}` implicit binding but code uses `{slug}` with manual resolution; arch testing table listed four unit tests that were never written) | Should Fix | Fixed | Updated `architecture.md`: (a) route parameter documented as `{slug}` with the rationale that `published()` must gate resolution, and (b) testing table rewritten to match the shipped suite — added `ResolveRequestLanguageTest`, removed the four never-built resource/request unit suites (feature coverage is comprehensive). |
| 3 | `resolve-language` applied to the whole `v1` group including `/auth/*` routes that never read language | Minor | Fixed | Middleware moved from the `v1` group to the `reading-plans` subgroup (alongside the `api-key` alias). Auth routes no longer carry a reading-plans-shaped dependency. |
| 4 | Redundant `status` field on the public list resource (always `"published"` by virtue of the `published()` scope; adds churn if the status model grows) | Minor | Fixed | Dropped `status` from `ReadingPlanResource::toArray`. Updated `ListReadingPlansTest::test_it_returns_the_expected_shape` accordingly. Public contract no longer exposes an always-constant field. |
| 5 | Duplicated api-key config in feature test `setUp` | Minor | Fixed | Extracted `Tests\Concerns\WithApiKeyClient` trait (`setUpApiKeyClient()` + `apiKeyHeaders()`). Applied to `ListReadingPlansTest` and `ShowReadingPlanTest`; future api-key-aware suites can reuse it. |
| 6 | Implicit coverage of `ResolveRequestLanguage` (only exercised transitively via feature tests) | Minor | Fixed | Added `tests/Unit/Http/Middleware/ResolveRequestLanguageTest.php` pinning supported-value, unsupported-value, missing-value, and non-string-value paths. Four new unit tests, all green. |

### Test Suite Results (after fixes)

- Command: `make check` (lint + stan + tests, Docker-scoped).
- Pint: **PASS** — 89 files, no style issues.
- PHPStan: **PASS** — 72/72, 0 errors.
- PHPUnit: **80 passed | 0 failed | 0 skipped** | 212 assertions | 0.98s.
  - Up from 76/205 at audit time (+4 tests for `ResolveRequestLanguageTest`).
- All previously reported issues resolved.

### Additional Changes

- Architecture doc now carries the request-attribute storage rationale for
  the resolved `Language`, matching the actual middleware implementation.
- Trait `Tests\Concerns\WithApiKeyClient` establishes a small testing
  convention: anything touching an api-key-protected route opts in via
  `use WithApiKeyClient;` and calls `$this->setUpApiKeyClient()` from
  `setUp()`. Future MBA-003 / MBA-004 suites can reuse it immediately.

---

## Part 2 — Workflow Improvement Proposals

### High Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 1 | `~/.claude/agents/architect.md` | Add an explicit checklist line to the "HTTP endpoints" section: *when documenting an implicit route-model binding (`{model:field}`), call out any domain scope (e.g. `published()`) that would make implicit binding incorrect, and prescribe manual resolution + exception throw when the scope must gate resolution*. | In MBA-001, architecture specified `{plan:slug}` implicit binding but the `published()` scope is required to 404 on drafts — implicit binding would either need a `resolveRouteBinding` override or leak drafts. The Engineer correctly pivoted to manual resolution; the Reviewer/Auditor then flagged the drift because the architect hadn't anticipated the conflict. Adding a "scope-aware binding" prompt would have caught this at architecture time. |
| 2 | `~/.claude/agents/architect.md` | Add a guideline in the "Testing Strategy" section: *if the feature tests already cover a resource/request behavior end-to-end, decide at architecture time whether to list dedicated unit suites — and when listed, explain why they add coverage beyond the feature tests*. | Architecture.md listed four unit suites (resource + request tests) that the Engineer skipped because the feature tests already covered the same behavior. The Reviewer and Auditor both had to flag the omission. Deciding upfront — and documenting the rationale — removes the ambiguity. |

### Medium Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 3 | `~/.claude/docs/laravel-beyond-crud-guidelines.md` (or MBA's project CLAUDE.md) | Add a short guideline: *prefer `$request->attributes` to ad-hoc container bindings for middleware→resource data-passing*. Reference Laravel's built-in convention of attaching `api_client` (matched key name from MBA-002) and show the fallback pattern (`->get(KEY, $default)`) so resources never hard-fail on a missing middleware. | Two independent pieces of code in this project (`EnsureValidApiKey` using `$request->attributes->set('api_client', …)` and `ResolveRequestLanguage` originally using `app()->instance(...)`) chose different mechanisms for the same problem. Unifying on the `$request->attributes` pattern is smaller-surface, safer (no global state), and self-documents via parameter passing. The audit caught this for `ResolveRequestLanguage`; the guideline would steer the next middleware author toward the right default. |
| 4 | `~/.claude/agents/code-reviewer.md` | Add a checklist item under the MBA JSON-API overrides section: *flag any public-facing API field whose value is constant at the query-scope level (e.g. a `status` column returned after a `where('status', …)` filter). Constant fields clutter the contract and create churn when the domain enum grows*. | `status` was listed on the public `ReadingPlanResource` despite being always `"published"` thanks to the `published()` scope. The Reviewer flagged it as a Suggestion and the Auditor echoed it as Minor. A named rule would push it from "nice-to-have catch" into "standard review question". |

### Low Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 5 | `.agent-workflow/CLAUDE.md` (project) | Add a short "Test helpers" section listing known trait concerns (starting with `Tests\Concerns\WithApiKeyClient`) and the convention of adding new ones under `tests/Concerns/`. | The trait extracted in this story is a natural home for every future api-key-aware test. Documenting it prevents MBA-003 / MBA-004 from re-inventing the `config()->set('api_keys.…')` boilerplate. |
| 6 | `~/.claude/agents/auditor.md` | When the Auditor lists Minor items that accumulate into a meaningful cleanup (e.g. four in this story), prompt the Improver to verify each one gets a pass/fail in the output table, not just the Should Fix items. | Current rules treat Minor as "if time permits". In practice they represent the long tail of code-quality friction. Explicitly classifying each in `improvements.md` (done/skipped-with-reason) turns them from "nice to have" into trackable outcomes, which this improvements.md already does — codifying the pattern makes it the default. |

### Observations

- Two pieces of shared infrastructure emerged from this catalog story that
  will live beyond it: the `Language` enum + `LanguageResolver` and the
  per-request middleware pattern that attaches resolver outputs to
  `$request->attributes`. Both are reusable. If future MBAs touch any
  user-facing strings or per-request resolution, promoting them from
  `Domain\ReadingPlans\Support` into `Domain\Shared\Support` (and adding a
  single container-binding migration guide) should happen the first time a
  second domain needs them, not retroactively.
- The `in:en,ro,hu` / `Language::fromRequest()` tug-of-war between AC 7
  and the architecture doc — resolved in this story by dropping the `in:`
  rule — is a candidate for a domain-level rule (`ValidLanguageOrDefault`)
  so that any future endpoint inherits the fallback semantics without
  re-deriving them. The Auditor already floated this as a recommendation;
  a future infrastructure story is the right home.
- The Reviewer and Auditor caught the same items (route binding drift,
  missing resource/request unit tests, container-bound language lookup,
  `status` field, duplicated api-key config). That redundancy is healthy
  defence in depth, but it also hints that the Architect checklist is the
  earliest, cheapest place to intercept these — hence proposals #1 and #2.
