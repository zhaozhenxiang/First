---
name: run-test
description: Intelligently run PHP tests based on changed files or specified module
user-invocable: true
---

# Run Test

Run the most relevant PHP tests based on changed files or a specified module.

## Usage

```
/run-test                    # Auto-detect from git diff
/run-test Database           # Run tests for a specific module
/run-test Auth Authorization # Run tests for multiple modules
/run-test full               # Run full test suite
```

## File-to-Test Mapping

When auto-detecting or mapping module names, use this table:

| Source Directory / Module | Test Files |
|--------------------------|------------|
| `bin/Database/*` / `Database` | `tests/QueryBuilderTest.php` `tests/ModelTest.php` `tests/CollectionTest.php` `tests/PaginatorTest.php` `tests/SoftDeletesTest.php` |
| `bin/Auth/*` / `Auth` | `tests/AuthTest.php` `tests/AuthorizationTest.php` `tests/RateLimiterTest.php` |
| `bin/Container/*` / `Container` | `tests/ContainerTest.php` |
| `bin/App/*` / `App` | `tests/AppTest.php` `tests/ContainerTest.php` |
| `bin/Route/*` / `Route` | `tests/RouteTest.php` |
| `bin/Request/*` / `Request` | `tests/RequestTest.php` |
| `bin/Response/*` / `Response` | `tests/ResponseTest.php` |
| `bin/View/*` / `View` | `tests/ViewTest.php` |
| `bin/Cache/*` / `Cache` | `tests/CacheTest.php` |
| `bin/Session/*` / `Session` | `tests/SessionTest.php` |
| `bin/Validation/*` / `Validation` | `tests/ValidationTest.php` |
| `bin/Console/*` / `Console` | `tests/ConsoleTest.php` |
| `bin/Events/*` / `Events` | `tests/EventSystemTest.php` |
| `bin/Cookie/*` / `Cookie` | `tests/CookieUploadTest.php` |
| `bin/Http/*` / `Http` | `tests/CookieUploadTest.php` |
| `bin/Log/*` / `Log` | `tests/LogTest.php` |
| `bin/Config/*` / `Config` | `tests/ConfigRepositoryTest.php` `tests/ConfigSystemTest.php` |
| `bin/Middleware/*` / `Middleware` | `tests/MiddlewareTest.php` |
| `bin/Facade/*` / `Facade` | `tests/FacadeTest.php` |
| `bin/Exception/*` / `Exception` | `tests/ExceptionHandlerTest.php` |
| `bin/Func/*` / `Helpers` | `tests/HelperTest.php` |

## Behavior

1. If user specifies module name(s): map to test files from the table above
2. If no argument or `auto`: run `git diff --name-only HEAD` to find changed files, map each to test files
3. If `full`: run `php test` (full suite)
4. Deduplicate test files, then run: `php test <test_files>`
5. Report results concisely — pass count, failures with file:line
