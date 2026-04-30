# Review — MBA-022-mb-013-handoff-implementation

## Summary

Round 3. The single Warning from round 2 (Olympiad reorder leaving stale
public-read cache) is resolved by commit `8bd5cb0`: the action now flushes
`OlympiadCacheKeys::tagsForTheme($book, $range, $language)` after the
transaction commits, and a new feature test
(`ReorderOlympiadQuestionsTest::test_reorder_invalidates_the_public_read_cache`)
primes the cache via the public read path, performs the reorder, and
asserts the tagged cache entry is gone. Stan is clean and the targeted
suite (8 tests, 23 assertions for `ReorderOlympiad*`) passes.

The fix uses the existing tag set, which is the same set the read path
attaches via `FetchOlympiadThemeQuestionsAction.php:84`, so the flush is
guaranteed to invalidate the cached payload. Flushing on the
theme-scoped tag (rather than the root `oly` tag) is the right blast
radius — it leaves the themes-list cache untouched, which is correct
since reorder doesn't change theme membership.

The earlier round-2 Suggestions (interface-based shuffler/reorder rules,
extracting the shared `count() !== count($ids)` reorder check across the
five reorder actions, the `position`-default drift between migrations
and backfill, the duplicated rules in
`ReorderOlympiadQuestionsRequest`) were explicitly non-blocking and
remain non-blocking; they are tracked for future stories or the
deferred-extractions register.

## Verdict

**APPROVE** — status flips to `qa-ready`.

## Notes (acknowledged out-of-scope)

- The deferred-extractions register
  (`apps/api/.agent-workflow/CLAUDE.md` §7) still doesn't reflect the
  five copies of the new `count() !== count($ids)` reorder check or the
  five `return $this->user() !== null` owner-`authorize()` blocks raised
  in earlier rounds. Architect's call on whether either deserves a row;
  no blocker for this story.
- The `position` default-vs-backfill drift on the three migrations
  (`olympiad_questions`, `educational_resources`, `resource_categories`)
  remains as flagged in round 2's Suggestions. New rows insert at
  `position = 0` while backfilled rows start at `1`, so newly-created
  rows sort *before* every backfilled row in the canonical order until
  an admin reorders. Acknowledged as out-of-scope for this story.
- `SeededShuffler` and shared `ReorderRequest` losing `final` to
  accommodate test fakes / a single subclass remains a code-shape choice
  the Engineer made deliberately; the cleaner DI-bound interface
  refactor is non-blocking and tracked for follow-up.
- Round 1 Suggestions (factory polish, login-action `final` placement,
  unpaginated admin user list) remain unaddressed by design — the
  Engineer treated them as non-blocking, consistent with the agent
  contract.
