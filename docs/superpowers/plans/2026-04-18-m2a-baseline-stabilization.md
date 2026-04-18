# M2-A Baseline Stabilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Status:** Completed on 2026-04-19 on branch `m2a-web-state-foundation`.
> The original header-safe baseline goal was completed, but final full-suite verification exposed additional hidden baseline issues outside the initial two-file scope. Those follow-up fixes were completed in the same branch before returning the plan to done state.

**Goal:** Make the CLI `php test` runner safe for session/cookie code so `tests/SessionTest.php` and `tests/CookieUploadTest.php` run cleanly before `M2-A` implementation starts.

**Architecture:** Fix the root cause in `bin/Testing/TestCommand.php`, not in `SessionManager` or `CookieManager`. The runner currently prints progress before the tested code tries to start sessions or set cookies, so the plan adds script-level regression tests around the real `php test` entrypoint and wraps runner output in a top-level output buffer to keep headers unsent until execution finishes.

**Tech Stack:** PHP 8.3+, custom `php test` runner, `TestCase` subprocess assertions via `exec()`, CLI output buffering with `ob_start()`.

---

## File Structure

- Modify: `tests/TestCommandScriptTest.php`
  Responsibility: add end-to-end regressions for the real `php test` script when running `SessionTest` and `CookieUploadTest`.
- Modify: `bin/Testing/TestCommand.php`
  Responsibility: keep runner progress output buffered so session/cookie headers are not marked as sent during test execution.
- Verify: `tests/SessionTest.php`
  Responsibility: confirm the existing session suite passes once runner output is header-safe.
- Verify: `tests/CookieUploadTest.php`
  Responsibility: confirm the existing cookie suite stops printing `Cannot modify header information` warnings once runner output is header-safe.

---

### Task 1: Add Regression Coverage for Header-Safe Test Execution

**Files:**
- Modify: `tests/TestCommandScriptTest.php`

- [x] **Step 1: Write the failing regression tests**

Append these methods to `tests/TestCommandScriptTest.php`:

```php
public function testRootTestScriptRunsSessionSuiteWithoutHeaderSentFailure(): void
{
    $script = basePath('test');
    $testFile = basePath('tests/SessionTest.php');

    $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $rendered = implode("\n", $output);

    $this->assertEquals(0, $exitCode);
    $this->assertStringContainsString('Passed: 25, Failed: 0', $rendered);
    $this->assertStringNotContainsString('Session cannot be started after headers have already been sent', $rendered);
    $this->assertStringNotContainsString('data_set(): Argument #1 ($data) must be of type array, null given', $rendered);
}

public function testRootTestScriptRunsCookieSuiteWithoutHeaderWarnings(): void
{
    $script = basePath('test');
    $testFile = basePath('tests/CookieUploadTest.php');

    $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $rendered = implode("\n", $output);

    $this->assertEquals(0, $exitCode);
    $this->assertStringContainsString('Passed: 22, Failed: 0', $rendered);
    $this->assertStringNotContainsString('Cannot modify header information', $rendered);
}
```

- [x] **Step 2: Run the script-level regression tests to verify they fail**

Run: `php test tests/TestCommandScriptTest.php`

Expected: FAIL with both regressions red:

```text
Failed asserting that string contains 'Passed: 25, Failed: 0'
Failed asserting that string does not contain 'Cannot modify header information'
```

- [x] **Step 3: Inspect the actual failing output once**

Run: `php test tests/SessionTest.php`

Expected: Output still contains:

```text
Session cannot be started after headers have already been sent
data_set(): Argument #1 ($data) must be of type array, null given
```

- [x] **Step 4: Inspect the cookie warning path once**

Run: `php test tests/CookieUploadTest.php`

Expected: Output still contains:

```text
Cannot modify header information - headers already sent
```

### Task 2: Buffer Runner Output So Headers Stay Unsent During Test Execution

**Files:**
- Modify: `bin/Testing/TestCommand.php`
- Modify: `tests/TestCommandScriptTest.php`

- [x] **Step 1: Implement a top-level buffered execution wrapper**

In `bin/Testing/TestCommand.php`, add this helper method inside the class:

```php
private function runWithBufferedOutput(callable $callback): int
{
    $initialLevel = ob_get_level();
    ob_start();

    try {
        return (int) $callback();
    } finally {
        while (ob_get_level() > $initialLevel) {
            ob_end_flush();
        }
    }
}
```

Then replace `public function run(): int` with:

```php
public function run(): int
{
    return $this->runWithBufferedOutput(function (): int {
        if ($this->singleTestFile !== null) {
            return $this->runTestFile($this->singleTestFile);
        }

        echo "Testing Framework\n";
        echo "==================\n\n";

        $runner = new TestRunner($this->testPath);

        if ($this->verbose) {
            $runner->setVerbose(true);
        }

        if ($this->stopOnFailure) {
            $runner->setStopOnFailure(true);
        }

        $runner->setPattern($this->pattern);

        if ($this->filter !== null) {
            $runner->setFilter($this->filter);
        }

        $summary = $runner->run();

        $summary->output();

        return $summary->getExitCode();
    });
}
```

- [x] **Step 2: Run the script regression tests again**

Run: `php test tests/TestCommandScriptTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 3: Run the previously broken session suite directly**

Run: `php test tests/SessionTest.php`

Expected: PASS with:

```text
Passed: 25, Failed: 0
```

and without:

```text
Session cannot be started after headers have already been sent
data_set(): Argument #1 ($data) must be of type array, null given
```

- [x] **Step 4: Run the cookie suite directly**

Run: `php test tests/CookieUploadTest.php`

Expected: PASS with:

```text
Passed: 22, Failed: 0
```

and without:

```text
Cannot modify header information
```

- [x] **Step 5: Commit the baseline stabilization**

```bash
git add bin/Testing/TestCommand.php tests/TestCommandScriptTest.php
git commit -m "fix: buffer test runner output for header-safe execution"
```

### Task 3: Verify the Baseline Is Stable Before Returning to M2-A

**Files:**
- Verify: `tests/TestCommandScriptTest.php`
- Verify: `tests/SessionTest.php`
- Verify: `tests/CookieUploadTest.php`
- Verify: `tests/ApplicationLifecycleTest.php`
- Verify: `tests/RequestTest.php`
- Verify: `tests/MiddlewareTest.php`
- Verify: `tests/ResponseTest.php`

- [x] **Step 1: Re-run the script-level coverage**

Run: `php test tests/TestCommandScriptTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 2: Re-run the two previously unstable suites**

Run: `php test tests/SessionTest.php`

Expected: PASS with `Failed: 0`.

Run: `php test tests/CookieUploadTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 3: Re-run the rest of the already-green M2-A baseline**

Run: `php test tests/ApplicationLifecycleTest.php`

Expected: PASS with `Failed: 0`.

Run: `php test tests/RequestTest.php`

Expected: PASS with `Failed: 0`.

Run: `php test tests/MiddlewareTest.php`

Expected: PASS with `Failed: 0`.

Run: `php test tests/ResponseTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 4: Confirm final changed-file scope and record deviations**

Run: `git diff --name-only ec718555823fa39d03618ec4fac3685a55f20a33..HEAD`

Actual: the original two-file expectation did not hold. Final full-suite verification surfaced additional hidden baseline issues, so the stabilized branch also updated `tests/SessionTest.php`, `bin/Testing/TestCase.php`, `bin/Resource/ResourceCollection.php`, `tests/ErrorPathTest.php`, `tests/ViewTest.php`, and `tests/FacadeExpandTest.php`.

---

## Self-Review Notes

- Spec coverage:
  - `SessionTest` root cause is covered by Task 1 regression + Task 2 runner buffering + Task 3 re-run.
  - `CookieUploadTest` warning root cause is covered by Task 1 regression + Task 2 runner buffering + Task 3 re-run.
  - Final full-suite verification also exposed and resolved hidden runner/error-path/view-fixture issues before plan closure.
- Placeholder scan:
  - This plan contains exact file paths, exact code to add, exact commands, exact expected failure strings, and a concrete commit message.
- Type consistency:
  - The buffering helper is `runWithBufferedOutput()`.
  - The implementation stays in `TestCommand`, not `SessionManager` or `CookieManager`.
