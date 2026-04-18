# Improvements — MBA-004-reading-plan-lifecycle

## Part 1 — Code Fixes

### Issues Addressed

| # | Issue (from audit) | Severity | Status | What was done |
|---|---|---|---|---|
| 1 | `RescheduleReadingPlanSubscriptionAction` re-parses `CarbonImmutable` via `Carbon::parse(...->toDateString())` | Minor | Fixed | Replaced the string round-trip with `Carbon::instance($data->startDate)` (and `Carbon::instance($data->startDate->addDays($index))`). The direct-assignment form suggested in the audit (`$subscription->start_date = $data->startDate;`) trips PHPStan because the model PHPDoc types the property as the mutable `Carbon`, not `CarbonInterface`; `Carbon::instance()` is the minimal conversion that keeps the code readable and the analyser happy. Behaviour is unchanged (18 Reschedule tests still pass). **The PHPDoc type is the root cause of this smell — see Part 2 Proposal #2 for the durable fix.** |
| 2 | `withProgressCounts()` duplicated between `FinishReadingPlanSubscriptionAction` and `AbandonReadingPlanSubscriptionAction` | Minor | Deferred | Auditor explicitly deferred: _"flag for the next eligible follow-up rather than fixing speculatively."_ Two copies is below the rule-of-three threshold; extract when a third lifecycle Action (resume, restart, transfer) lands. Tracked via Part 2 Proposal #3 below. |
| 3 | Owner-`authorize()` block duplicated across the three new Form Requests plus MBA-003's `CompleteReadingPlanSubscriptionDayRequest` (four copies) | Minor | Deferred | Architecture explicitly deferred: _"If a fifth owner-gated endpoint arrives, extract then."_ Four copies is intentional; extraction at +1 is documented. Tracked via Part 2 Proposal #3 below. |

### Test Suite Results (after fixes)

- Total: 149 | Passed: 149 | Failed: 0.
- 397 assertions, 1.43s.
- Reschedule slice (`filter=Reschedule`): 18 passed.
- `make lint-fix` clean; `make stan` clean; `make test` clean.
- No regressions in MBA-001 / MBA-002 / MBA-003 coverage.

### Additional Changes

None beyond the audit fix. No refactors, no new features, no doc updates outside the story directory.

---

## Part 2 — Workflow Improvement Proposals

### High Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 1 | `~/.claude/docs/workflow.md` | **Promote MBA-003 Proposal #2 from proposal to workflow rule.** Add a short "Post-APPROVE cleanup" note before the Review → QA transition: _If a Code Review verdict is APPROVE but the review contains unchecked Suggestions, the Engineer addresses them (or explicitly skips with a one-line reason in `review.md`) before QA runs. Suggestion items must not silently survive to the audit._ | MBA-003 identified this leak (Suggestion → QA silent → Audit Minor → Improver fix). MBA-003 proposed the fix but did not apply it (Improver never modifies global files). MBA-004 repeats the exact pattern: Code Review flagged the `Carbon::parse(...->toDateString())` round-trip as a Suggestion, QA did not touch it, Audit re-flagged as Minor, Improver fixed. **Two consecutive stories with the same leak** is strong enough signal to act. |
| 2 | `~/.claude/docs/laravel-beyond-crud-guidelines.md` (Models / casts section) | Add a one-liner: _For `date` or `datetime` cast properties in model PHPDoc, prefer `CarbonInterface` over `Carbon`. Both `Carbon` and `CarbonImmutable` are valid runtime inputs to the cast; annotating the narrower type makes Action code fight PHPStan unnecessarily._ | Engineer wrote `Carbon::parse($data->startDate->toDateString())` to round-trip a `CarbonImmutable` into a string because direct assignment trips PHPStan against the `Carbon`-only PHPDoc on the model. That string round-trip then became a Code Review suggestion and an Audit Minor finding — the PHPDoc type was the **root cause** of the cosmetic smell. Broadening the annotation removes the friction at the source. |

### Medium Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 3 | `.agent-workflow/CLAUDE.md` (new short section, e.g. "Deferred extractions tripwire") | Maintain a short in-repo register of conscious duplication deferrals, keyed by pattern. Each entry names the pattern, the current copy-count, the extraction threshold, and the story where extraction is expected. Current entries: (a) owner-`authorize()` block — 4 copies (MBA-003 CompleteReadingPlanSubscriptionDayRequest + MBA-004 Reschedule/Finish/Abandon requests), extract at 5; (b) `withProgressCounts()` helper — 2 copies (MBA-004 Finish/Abandon actions), extract at 3. | Both deferrals are sensible individually, but they rely on the **next** Architect remembering to check them. MBA-004's audit report flags both; MBA-005's architect will have to re-discover them. A repo-owned register is the durable place — Architect consults it when planning, Auditor checks entries on pass, Improver updates counts on close. Prevents "four stories from now, nobody remembered we said we'd extract at N+1." |
| 4 | `~/.claude/agents/qa.md` | Add a dedicated **"Open product questions"** section to the qa-report template, distinct from "Observations." Use it for behaviours the ACs do not specify (e.g. finish-on-abandoned, reschedule on a terminal-status subscription) so they route to product rather than rotting in observation lists. | MBA-004 QA surfaced two AC-silent lifecycle transitions as end-notes: "Finish on `Abandoned`" and "Reschedule on `Completed`/`Abandoned`." Neither is a QA failure, neither is an Improver fix — they are product-scope questions. Today they land in a prose "Observations" block that no agent owns downstream. Auditor echoed them briefly. A structured section makes them a routable artifact. |

### Low Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 5 | `~/.claude/agents/architect.md` | Require the **Risks & open questions** section to be numbered (`R1`, `R2`, …) with each risk stating (a) the risk, (b) a walk-through or concrete example where relevant, (c) an explicit **Decision**, and (d) any regression guard the Engineer owes. MBA-004's architecture.md is a model example (R1–R5 with walk-throughs and explicit decisions). | The numbered-risks-with-decisions format in MBA-004 produced a clean downstream trace: R2's `after_or_equal:today` decision became a Form Request rule, a unit test case, and a feature test case (cited by PR description per the architect's instruction). Compare to MBA-001/002 architecture docs, where risks were narrative and untracked. Encoding the pattern avoids the narrative drift. |

### Observations

- **Leak pattern is confirmed repeat.** MBA-003 Proposal #2 called this out based on one occurrence; MBA-004 is the second. Promote the proposal in `workflow.md` now rather than waiting for a third datapoint.
- **MBA-003 Proposal #3** (Action naming parity with Controller/Request) was already applied to the guidelines and is visible as enforced convention in MBA-004: `RescheduleReadingPlanSubscriptionAction` / `…Controller` / `…Request`, `FinishReadingPlanSubscriptionAction` / `…Controller` / `…Request`, `AbandonReadingPlanSubscriptionAction` / `…Controller` / `…Request`. No review, QA, or audit friction on sibling naming this story. Proposal landed cleanly.
- **Zero Must/Should audit findings for the second story in a row** (MBA-003 had one Should Fix; MBA-004 had none). The pipeline is converging. The remaining noise is Minor cosmetics and duplication deferrals — i.e. exactly what Proposals #1 and #3 target.
- **PHPStan-induced rewrites are a recurring shape.** Engineer's round-trip in Reschedule, like MBA-003's `loadMissing` defensive-load, both trace back to "the analyser or a test would have failed without this guard." These are not bugs — they are papercuts. Worth watching for a third instance before proposing a broader rule; for now Proposal #2 closes the specific Carbon case.
- **MBA-004 touched zero migrations and zero models.** The architecture leaned entirely on the MBA-003 schema and factories — a good sign that MBA-003's groundwork was planned correctly. No proposal here; noting that "a story ships with no migration" is a healthy signal that the predecessor story left the domain in the right shape.
