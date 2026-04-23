# QA — MBA-015 Olympiad

## Test runs

- `make test filter=Olympiad` — 32 passed / 123 assertions / 0.78s.
- `make test` (full) — 407 passed / 1276 assertions / 7.32s. No regressions.

## AC → Test coverage

| AC | Test file:line |
|---|---|
| 1. Themes endpoint + response shape (`id, book, chapters_from, chapters_to, language, question_count`) | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php:31`, `:88`; `tests/Unit/Http/Resources/Olympiad/OlympiadThemeResourceTest.php:14` |
| 1. Paginated, default 50/page | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php:64`; `tests/Unit/Domain/Olympiad/Actions/ListOlympiadThemesActionTest.php:45` |
| 2. `api-key-or-sanctum` protection | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php:25`; `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:26` |
| 3. `Cache-Control: public, max-age=3600` | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php:77`; `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:131` |
| 4. Theme questions endpoint + shape (`id, question, answers[{id,text,is_correct}], explanation`) | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:32`; `tests/Unit/Http/Resources/Olympiad/OlympiadQuestionResourceTest.php:16` |
| 4. Chapter range `1-3` and single `5` segments | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:98` |
| 4. Seed param honored / echoed / questions randomized stably | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:51`; `tests/Unit/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsActionTest.php:36`, `:67`, `:82` |
| 5. 404 when no questions match | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:76`; `tests/Unit/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsActionTest.php:22` |
| 6. Answer ordering randomized & stable per seed | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:70`; `tests/Unit/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsActionTest.php:56` |
| 7. Language filter on themes | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesControllerTest.php:50`; `tests/Unit/Domain/Olympiad/QueryBuilders/OlympiadQuestionQueryBuilderTest.php:17` |
| 7. Language fallback is 404, not silent | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeControllerTest.php:84` |
| 7. Malformed/inverted chapters, unknown book → 422 | `ShowOlympiadThemeControllerTest.php:108`, `:116`, `:123` |
| 8. Action unit tests with seed determinism | `tests/Unit/Domain/Olympiad/Actions/FetchOlympiadThemeQuestionsActionTest.php:36`, `:67`, `:82`; `tests/Unit/Domain/Olympiad/Actions/ListOlympiadThemesActionTest.php:18`, `:45` |

## Findings

- All acceptance criteria mapped to passing tests.
- Review verdict was APPROVE with 0 Critical (2 Warnings acknowledged).
- Full suite: no regressions introduced by this story.

## Verdict

QA PASSED
