# Improvements — MBA-002-auth-foundation

## Part 1 — Code Fixes

Audit verdict was **AUDIT PASSED** — 0 Must-Fix, 0 Should-Fix, 6 Minor
notes. The Improver addressed five of the six Minor items (one is
explicitly deferred by story scope).

### Issues Addressed

| # | Issue (from audit) | Status | What was done |
|---|---|---|---|
| 1 | `LoginUserAction::DUMMY_HASH` was a frozen `$2y$12$…` literal — diverges from real-user hash cost if `BCRYPT_ROUNDS` is changed in `.env`. | ✅ Fixed | Replaced the constant with a lazily-computed, memoized static (`self::$dummyHash`). `Hash::make('dummy-password')` is called once per process on the first unknown-email request so the dummy-path cost tracks `config('hashing.bcrypt.rounds')`. |
| 2 | `EnsureValidApiKey` had a bare `// TODO(rate-limit)` comment on a dangling line between the class opener and `handle()`. | ✅ Fixed | Promoted the marker to a class-level docblock with a `@todo` tag and kept the rate-limiting context tied to the `api_client` attribute. |
| 3 | `LogoutCurrentTokenAction` `@phpstan-ignore instanceof.alwaysTrue` docblock described only the `TransientToken` case; the `null` path is what one of the unit tests actually exercises. | ✅ Fixed | Expanded the docblock to explicitly cover both `null` (unauthenticated caller) and `TransientToken` (session-guard authentication). |
| 4 | `LoginUserTest::test_it_returns_a_token_for_valid_credentials` relied on the `User` model's `hashed` cast to hash the factory password — correct but implicit for a future reader. | ✅ Fixed | Added a one-line comment above the `User::factory()->create(...)` call documenting the implicit cast dependency. |
| 5 | `config/sanctum.php` retained Sanctum's default `SANCTUM_STATEFUL_DOMAINS` list (`localhost:3000`, `127.0.0.1:8000`, …). Dead weight for an API-only posture with `'guard' => []`. | ✅ Fixed | Replaced the default with `array_values(array_filter(explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))))` and rewrote the comment to flag that cookie-auth would require an explicit env value. Dropped the now-unused `Laravel\Sanctum\Sanctum` import. |
| 6 | `auth.login` / `auth.register` have no brute-force throttling. | ⏭ Deferred | Explicitly out of scope for MBA-002 per the story's rate-limit deferral. Carried into the Part 2 proposals as "track deferred items as follow-up stories." |

### Test Suite Results (after fixes)

- Lint: **PASS** (59 files, Laravel Pint)
- PHPStan: **PASS** (0 errors, 42 files)
- Tests: **49 passed / 126 assertions / 0 failed / 0.77s**

All previously reported issues resolved; no regressions.

### Additional Changes

None. The Improver did not introduce new functionality, refactor outside
the audit findings, or touch unrelated files.

---

## Part 2 — Workflow Improvement Proposals

Per `.agent-workflow/CLAUDE.md`, proposals target the project-level
override file first, not the global agent files.

### High Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 1 | `.agent-workflow/CLAUDE.md` (add a new subsection to **§4 Engineer** overrides) | Add a rule: *"Never embed environment-dependent costs (bcrypt rounds, cache TTLs, token expirations) as frozen literals when the same cost is configurable elsewhere. Derive from `config(...)` or compute once and memoize."* | In MBA-002, `LoginUserAction::DUMMY_HASH` was a literal `$2y$12$…` hash. Both the Code Reviewer (`review.md` Suggestion #1) and the Auditor (`audit-report.md` Minor #1) flagged the coupling between the literal and `config('hashing.bcrypt.rounds')`. An explicit rule would have caught this at Engineer time. |
| 2 | `.agent-workflow/CLAUDE.md` (add to **§6 Project-Specific Rules**) | Add a rule: *"When a user story explicitly defers a security-adjacent concern (rate limiting, auth throttling, MFA), the Architect's `architecture.md` must end with a **Follow-up Stories** section naming the concrete next story ID and its minimum acceptance criterion."* | MBA-002 defers rate-limiting. Both `review.md` and `audit-report.md` carry the concern forward as a note, but no follow-up story has been created. A named slot in `architecture.md` forces the deferral to be tracked as a concrete artifact rather than drifting as prose. |
| 3 | `.agent-workflow/CLAUDE.md` (add to **§4 Code Reviewer** overrides) | Add a checklist item: *"When a package's config file is published into `config/`, unused package defaults (stateful domains, guards, drivers) must be trimmed or nulled out to match the project posture. Flag any published config that retains defaults the project cannot hit."* | `config/sanctum.php` retained the full stateful-domains list from Sanctum's published defaults. The Code Reviewer (`review.md`) missed it; the Auditor (`audit-report.md` Minor #6) caught it. Adding it to the reviewer checklist prevents dead-weight config from landing in future stories that publish package configs. |

### Medium Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 4 | `.agent-workflow/CLAUDE.md` (add to **§4 Engineer** overrides) | Add a convention: *"TODO / FIXME markers belong in a docblock on the enclosing class or method, or inline at the exact line they apply to — never on a dangling line between the class opener and the first member."* | `EnsureValidApiKey` had a bare `// TODO(rate-limit)` between `{` and `handle()`. Two reviewers flagged it (Suggestion #2, Minor #2). A one-line convention prevents the recurrence. |
| 5 | `.agent-workflow/CLAUDE.md` (add to **§2 Testing** section) | Document the "throwaway test route" pattern for middleware-in-isolation feature tests: *"To exercise a middleware directly, register an ad-hoc route inside the test's `setUp()` (or inline) and bind the middleware via `Route::middleware(...)`. Assert against `$request->attributes`, status codes, and JSON envelopes. Do not reuse production routes for middleware-permutation coverage."* | `EnsureValidApiKeyTest` and `EnsureApiKeyOrSanctumTest` both used this pattern effectively (six permutations for the combined middleware). Codifying it saves future Engineers the design step. |

### Low Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 6 | `.agent-workflow/CLAUDE.md` (add to **§4 QA** overrides) | Add a note: *"When a test depends on an implicit Eloquent cast (`hashed`, `encrypted`, etc.) for factory data, add a one-line comment above the `factory()->create(...)` call identifying the cast. This surfaces the hidden dependency for future readers."* | `LoginUserTest` relied on the `hashed` cast with no documentation. The Auditor (Minor #4) flagged it. Low impact because the pattern is correct; the comment just aids readability. |
| 7 | `~/.claude/agents/auditor.md` (global — propose only) | Consider adding a **Published-config hygiene** check to the project-specific-overrides slot, so the reviewer catches stale defaults without the auditor having to. Complements proposal #3 above. | Single occurrence in MBA-002; flag-for-pattern rather than a hard rule. |

### Observations

- **Workflow strength — resolved open questions up front.** `story.md`
  ended with three open questions; `architecture.md` opened with a
  "Resolved Open Questions" section answering each. No question leaked
  into implementation as ambiguity. This pattern should be preserved and
  is already implicit in the Architect agent; no change proposed, but
  worth noting as a workflow win.
- **Workflow strength — reuse of `Authenticate::class` in combined
  middleware.** `EnsureApiKeyOrSanctum` delegates to Laravel's own
  `Authenticate` middleware rather than re-implementing Sanctum's bearer
  resolution. Architect flagged this as a risk (framework-signature
  coupling) and QA verified it with six test permutations. This is a
  clean pattern worth preserving but not generalizing without a second
  example.
- **Recurring friction not visible yet.** MBA-002 is the second
  completed story (MBA-001 is the first). None of the above proposals
  reflect a repeat pattern across multiple stories — they all stem from
  single occurrences in MBA-002. Re-evaluate priority after MBA-003 /
  MBA-004 land to see which proposals map to patterns rather than
  isolated events.
- **No test gaps uncovered by the Auditor.** Test coverage scored 5/5
  with no edge cases missed. No changes proposed to QA or Engineer test
  guidance on that axis.
