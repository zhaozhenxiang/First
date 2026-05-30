# Developer API Request Validation Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add request-level validation helpers so ordinary `Request` instances can validate input and expose the most recent successful validated data.

**Architecture:** Keep rule execution inside `ValidationManager` and add only a narrow convenience layer to `Request`. `Request::validate()` creates a validator from `Request::all()`, applies optional custom messages and attribute aliases, throws the existing `ValidationException` on failure, and stores the successful result for `Request::validated()`.

**Tech Stack:** PHP 8.3+, First framework `Request`, `ValidationManager`, `ValidationException`, custom `php test` runner, graphify knowledge graph.

---

## Spec

- Source spec: `docs/superpowers/specs/2026-05-31-developer-api-request-validation-polish-design.md`
- Parent spec: `docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`
- Follow-up phase: "Broader facade/API polish across request, response, validation, resources, and controller helpers."
- This slice intentionally does not add new validation rules, new validation facade methods, named error bags, FormRequest lifecycle changes, exception handler response-shape changes, or controller base helpers.

## File Structure

- Modify `bin/Request/Request.php`
  - Responsibility: expose `validate()` and `validated()` on ordinary requests, store the latest successful validated data, and delegate rule execution to `ValidationManager`.
- Modify `tests/RequestTest.php`
  - Coverage: request validation success, merged input sources, dotted keys, optional missing fields, exception behavior, custom messages, attribute aliases, and validated data storage.
- Verify `tests/FormRequestTest.php`
  - Coverage: existing FormRequest validated-data behavior remains unchanged.
- Verify `tests/ValidationTest.php` and `tests/ValidationEngineTest.php`
  - Coverage: existing validation engine behavior remains unchanged.
- Verify `tests/DispatcherIntegrationTest.php`
  - Coverage: existing FormRequest controller injection remains unchanged.
- Verify `tests/ExceptionHandlerTest.php`
  - Coverage: existing `ValidationException` rendering remains unchanged.
- Update `graphify-out/` by running `graphify update .` after code changes.

---

### Task 1: Request Validation API

**Files:**
- Modify: `tests/RequestTest.php`
- Modify: `bin/Request/Request.php`

- [ ] **Step 1: Add failing request validation tests**

In `tests/RequestTest.php`, add this import after the namespace declaration:

```php
use Bin\Exception\ValidationException;
```

Keep the existing imports:

```php
use Bin\Testing\TestCase;
use Bin\Request\Request;
use Bin\Facade\URL;
use Bin\Route\RouteCollection as Route;
```

In `tests/RequestTest.php`, add these methods after `testInputReturnsAllWhenNoKey()`:

```php
    public function testValidateReturnsDeclaredValidatedDataAndStoresIt(): void
    {
        $request = $this->makeRequest(
            query: ['page' => '2', 'extra' => 'ignored'],
            post: ['name' => 'Ada', 'email' => 'ada@example.com']
        );

        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'page' => 'required|integer',
        ]);

        $this->assertEquals([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'page' => '2',
        ], $validated);
        $this->assertEquals($validated, $request->validated());
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testValidateUsesMergedRequestInputSources(): void
    {
        $request = $this->makeRequest(
            query: ['source' => 'query'],
            post: ['name' => 'Ada']
        );
        $request->merge(['role' => 'admin', 'source' => 'merged']);

        $validated = $request->validate([
            'source' => 'required|string',
            'name' => 'required|string',
            'role' => 'required|string',
        ]);

        $this->assertEquals([
            'source' => 'merged',
            'name' => 'Ada',
            'role' => 'admin',
        ], $validated);
    }

    public function testValidateSupportsJsonStyleMergedInputAndDottedRules(): void
    {
        $request = $this->makeRequest(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $request->merge([
            'profile' => ['name' => 'Ada'],
            'email' => 'ada@example.com',
        ]);

        $validated = $request->validate([
            'profile.name' => 'required|string',
            'email' => 'required|email',
        ]);

        $this->assertEquals([
            'profile.name' => 'Ada',
            'email' => 'ada@example.com',
        ], $validated);
    }

    public function testValidateOmitsAbsentOptionalFields(): void
    {
        $request = $this->makeRequest(post: ['name' => 'Ada']);

        $validated = $request->validate([
            'name' => 'required|string',
            'nickname' => 'string',
        ]);

        $this->assertEquals(['name' => 'Ada'], $validated);
        $this->assertArrayNotHasKey('nickname', $validated);
    }

    public function testValidateThrowsValidationExceptionForInvalidInput(): void
    {
        $request = $this->makeRequest(post: ['email' => 'not-an-email']);

        try {
            $request->validate([
                'email' => 'required|email',
                'name' => 'required|string',
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertEquals(422, $e->getCode());
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('name', $errors);
            $this->assertIsArray($errors['email']);
            $this->assertIsArray($errors['name']);
        }

        $this->assertEquals([], $request->validated());
    }

    public function testValidateAppliesCustomMessagesAndAttributeAliases(): void
    {
        $request = $this->makeRequest(post: ['email' => 'not-an-email']);

        try {
            $request->validate(
                [
                    'email' => 'required|email',
                    'name' => 'required|string',
                ],
                ['email.email' => 'Email must be valid'],
                ['name' => 'Display name']
            );
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertEquals('Email must be valid', $errors['email'][0] ?? '');
            $this->assertStringContainsString('Display name', $errors['name'][0] ?? '');
        }
    }

    public function testValidatedReturnsLatestSuccessfulResultAndSurvivesLaterFailure(): void
    {
        $request = $this->makeRequest(post: [
            'name' => 'Ada',
            'email' => 'ada@example.com',
        ]);

        $this->assertEquals([], $request->validated());

        $first = $request->validate(['name' => 'required|string']);
        $this->assertEquals(['name' => 'Ada'], $first);
        $this->assertEquals($first, $request->validated());

        $second = $request->validate(['email' => 'required|email']);
        $this->assertEquals(['email' => 'ada@example.com'], $second);
        $this->assertEquals($second, $request->validated());

        try {
            $request->validate(['missing' => 'required']);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            $this->assertEquals($second, $request->validated());
        }
    }
```

- [ ] **Step 2: Run request tests and verify the new tests fail**

Run:

```bash
php test tests/RequestTest.php
```

Expected: FAIL because `Bin\Request\Request::validate()` and `Bin\Request\Request::validated()` do not exist.

- [ ] **Step 3: Add request validation imports and state**

In `bin/Request/Request.php`, add this import with the existing imports:

```php
use Bin\Validation\ValidationManager;
```

Add this property after the existing `$mergedInput` property:

```php
    /** @var array<string, mixed> 最近一次成功验证后的数据 */
    protected array $validatedData = [];
```

- [ ] **Step 4: Implement `Request::validate()` and `Request::validated()`**

In `bin/Request/Request.php`, add these methods after `except()` and before the `// 输入源判断` section:

```php
    /**
     * 验证当前请求输入并返回已验证数据。
     *
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        $input = $this->all();
        $validator = new ValidationManager($input, $rules);

        if ($messages !== []) {
            $validator->setCustomMessages($messages);
        }

        if ($attributes !== []) {
            $validator->setAliases($attributes);
        }

        $validator->validateOrFail();

        $validated = [];
        foreach (array_keys($rules) as $field) {
            $field = (string) $field;
            if (data_has($input, $field)) {
                $validated[$field] = data_get($input, $field);
            }
        }

        $this->validatedData = $validated;

        return $this->validatedData;
    }

    /**
     * 获取最近一次成功验证后的数据。
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }
```

- [ ] **Step 5: Run request tests and verify they pass**

Run:

```bash
php test tests/RequestTest.php
```

Expected: all request tests pass, including the new request validation tests.

- [ ] **Step 6: Commit request validation API**

Run:

```bash
git add tests/RequestTest.php bin/Request/Request.php
git commit -m "feat: add request validation helpers"
```

---

### Task 2: Compatibility Verification

**Files:**
- Verify: `tests/FormRequestTest.php`
- Verify: `tests/ValidationTest.php`
- Verify: `tests/ValidationEngineTest.php`
- Verify: `tests/DispatcherIntegrationTest.php`
- Verify: `tests/ExceptionHandlerTest.php`

- [ ] **Step 1: Run FormRequest compatibility tests**

Run:

```bash
php test tests/FormRequestTest.php
```

Expected: all FormRequest tests pass. This confirms `FormRequest::validated()` and the FormRequest lifecycle still behave independently from the new plain `Request` helpers.

- [ ] **Step 2: Run validation engine tests**

Run each command separately:

```bash
php test tests/ValidationTest.php
php test tests/ValidationEngineTest.php
```

Expected:
- `tests/ValidationTest.php`: all tests pass.
- `tests/ValidationEngineTest.php`: all tests pass.

- [ ] **Step 3: Run dispatcher FormRequest integration tests**

Run:

```bash
php test tests/DispatcherIntegrationTest.php
```

Expected: all dispatcher integration tests pass, including existing FormRequest injection and validation tests.

- [ ] **Step 4: Run exception handler validation rendering tests**

Run:

```bash
php test tests/ExceptionHandlerTest.php
```

Expected: all exception handler tests pass, confirming `ValidationException` rendering behavior is unchanged.

- [ ] **Step 5: Commit compatibility checkpoint only if files changed**

Run:

```bash
git status --short
```

Expected: no uncommitted changes. If this task only ran verification, do not create an empty commit.

---

### Task 3: Final Verification And Graph Update

**Files:**
- Verify: `bin/Request/Request.php`
- Verify: `tests/RequestTest.php`
- Update if changed: `graphify-out/`

- [ ] **Step 1: Run focused verification**

Run each command separately:

```bash
php test tests/RequestTest.php
php test tests/FormRequestTest.php
php test tests/ValidationTest.php
php test tests/ValidationEngineTest.php
php test tests/DispatcherIntegrationTest.php
php test tests/ExceptionHandlerTest.php
```

Expected: all focused suites pass with `0` failed tests.

- [ ] **Step 2: Run the full test suite**

Run:

```bash
php test
```

Expected: the full suite exits with code `0`. Existing PHP deprecation notices may appear, but there must be `0` failed tests.

- [ ] **Step 3: Update the project graph**

Run:

```bash
graphify update .
```

Expected: graph update completes successfully. It may leave no tracked file changes.

- [ ] **Step 4: Check status**

Run:

```bash
git status --short
```

Expected:
- no uncommitted code changes, or
- only graph output changes from `graphify update .`.

- [ ] **Step 5: Commit graph updates if graph files changed**

If `git status --short` shows tracked `graphify-out/` changes, run:

```bash
git add graphify-out
git commit -m "chore: update graph after request validation polish"
```

Expected: a commit is created only when graph files changed.

- [ ] **Step 6: Final implementation checkpoint**

Confirm all acceptance criteria from the spec:

- Plain `Request` instances expose `validate()` and `validated()`.
- `Request::validate()` validates merged request input from query, post, JSON-style merged input, and manually merged input.
- `Request::validate()` returns only fields declared by validation rules and omits absent optional fields.
- `Request::validated()` returns the latest successful validation result and returns an empty array before validation succeeds.
- Invalid input throws `ValidationException` with the existing error structure.
- Custom messages and attribute aliases are supported.
- Existing `FormRequest` behavior remains unchanged.
- Existing request, validation, FormRequest, dispatcher, and exception handler tests pass.
- Full test suite passes after implementation.

Expected: all items are backed by tests or verification output from the previous steps.
