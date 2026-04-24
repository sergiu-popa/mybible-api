# QA — MBA-017 Sabbath School

## Test runs

- `make test filter=SabbathSchool` — 55 passed / 166 assertions / 1.30s.
- `make test` (full) — 675 passed / 2071 assertions / 10.76s. No regressions.

## AC → Test coverage

| AC | Test file |
|---|---|
| 1. Lessons catalog — paginated (default 30), language scoped, newest first | `tests/Feature/Api/V1/SabbathSchool/ListSabbathSchoolLessonsTest.php` (`test_it_returns_paginated_lessons_newest_first`, `test_it_filters_by_language`, `test_it_defaults_per_page_to_30`, `test_it_excludes_unpublished_lessons`) |
| 2. Lesson detail shape (`segments.questions` nested) | `tests/Feature/Api/V1/SabbathSchool/ShowSabbathSchoolLessonTest.php::test_it_returns_the_lesson_with_segments_and_questions`; N+1 guarded by `test_it_avoids_n_plus_one_on_a_large_fixture` |
| 3. `api-key-or-sanctum` protection on catalog | `ListSabbathSchoolLessonsTest::test_it_rejects_missing_credentials`, `::test_it_accepts_sanctum_auth`, `ShowSabbathSchoolLessonTest::test_it_rejects_missing_credentials` |
| 4. `Cache-Control: public, max-age=3600` on both | `ListSabbathSchoolLessonsTest::test_it_sets_public_cache_headers`, `ShowSabbathSchoolLessonTest::test_it_sets_public_cache_headers` |
| 5. POST answer upserts per `(user_id, question_id)`; 201 insert / 200 overwrite | `SabbathSchoolAnswerTest::test_it_creates_an_answer_on_first_post_with_201`, `::test_it_overwrites_an_existing_answer_on_subsequent_post_with_200` |
| 6. GET caller's answer; 404 when none | `SabbathSchoolAnswerTest::test_get_returns_the_callers_answer`, `::test_get_returns_404_when_caller_has_no_answer` |
| 7. DELETE caller's answer (204); 404 when none | `SabbathSchoolAnswerTest::test_delete_removes_the_callers_answer_with_204`, `::test_delete_returns_404_when_caller_has_no_answer` |
| 8. Sanctum-gated; cross-user access denied (never accept `user_id` from body) | `SabbathSchoolAnswerTest::test_answer_endpoints_require_sanctum`, `::test_get_does_not_leak_other_users_answers`, `::test_delete_does_not_remove_another_users_answer`, `::test_upsert_does_not_overwrite_another_users_answer` |
| 9. Highlight toggle (201 create / 200 delete) | `SabbathSchoolHighlightTest::test_toggle_creates_a_highlight_on_first_call`, `::test_toggle_deletes_the_highlight_on_second_call` |
| 10. List caller's highlights for a segment | `SabbathSchoolHighlightTest::test_list_returns_only_the_callers_highlights_for_the_segment`, `::test_list_requires_segment_id` |
| 11. Favorite toggle with/without `segment_id` (sentinel 0 for whole lesson) | `SabbathSchoolFavoriteTest::test_toggle_creates_whole_lesson_favorite_when_segment_id_is_omitted`, `::test_toggle_removes_whole_lesson_favorite_on_second_call`, `::test_toggle_with_segment_id_creates_a_separate_row` |
| 12. List caller's favorites | `SabbathSchoolFavoriteTest::test_list_returns_callers_favorites` |
| 13. Feature tests across all listed scenarios | All files under `tests/Feature/Api/V1/SabbathSchool/` |
| 14. Unit tests for each Action | `tests/Unit/Domain/SabbathSchool/Actions/` (Upsert, Delete, ToggleHighlight, ToggleFavorite) |

## Edge cases probed

- Unauthenticated access to every caller-data endpoint → 401 (`test_*_endpoints_require_sanctum` across all four test files).
- Unparseable highlight passage → 422 via `InvalidSabbathSchoolPassageException` (`SabbathSchoolHighlightTest::test_toggle_rejects_unparseable_passage`, `ToggleSabbathSchoolHighlightActionTest::test_it_wraps_invalid_reference_as_domain_exception`).
- Draft lesson cannot have answers saved against it (`SabbathSchoolAnswerTest::test_it_rejects_save_on_draft_lessons`).
- Unpublished lesson returns 404 on detail (`ShowSabbathSchoolLessonTest::test_it_returns_404_for_unpublished_lessons`).
- Content length cap 10 000 chars enforced on answers (`SabbathSchoolAnswerTest::test_it_rejects_content_exceeding_the_max_length`).
- Favorite `segment_id` must belong to the given lesson (`SabbathSchoolFavoriteTest::test_toggle_rejects_segment_from_a_different_lesson`).
- Invalid language filter rejected (`ListSabbathSchoolLessonsTest::test_it_validates_the_language_filter`).
- Independent highlights on the same segment with different passages (`ToggleSabbathSchoolHighlightActionTest::test_different_passages_on_the_same_segment_are_independent`).
- Segment-level favorite does not disturb whole-lesson sentinel row (`ToggleSabbathSchoolFavoriteActionTest::test_segment_level_insert_does_not_touch_the_sentinel_row`).

## Findings

- All 14 acceptance criteria mapped to passing tests.
- Review verdict was APPROVE with 0 Critical and 0 unchecked Warnings (5 non-blocking Suggestions only).
- Full suite: no regressions introduced by this story (675 passed).

## Verdict

QA PASSED
