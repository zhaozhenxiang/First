# Developer API Request Validation Polish Design

Date: 2026-05-31
Status: Ready for implementation plan

## Goal

Add a Laravel-style validation entry point to ordinary `Request` instances so
controllers and route closures can validate request data without creating a
dedicated `FormRequest` class for simple cases.

This is the second scoped slice of Follow-Up Phase 3 from
`docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`.
The previous slice aligned controller and resource response normalization. This
slice focuses only on request-level validation convenience and keeps the
existing validation engine, exception handler, and `FormRequest` lifecycle
intact.

## Scope

### In Scope

- `Request::validate(array $rules, array $messages = [], array $attributes = []): array`
- `Request::validated(): array`
- Reuse `Bin\Validation\ValidationManager` for rule execution.
- Reuse `Bin\Exception\ValidationException` for validation failures.
- Store the most recent successful validation result on the `Request` instance.
- Support custom validation messages through `$messages`.
- Support attribute aliases through `$attributes`, matching
  `FormRequest::attributes()`.
- Keep `FormRequest` validation behavior unchanged.
- Add focused tests for successful request validation, failure behavior,
  validated data storage, custom messages, attribute aliases, JSON input, and
  existing FormRequest compatibility.

### Out of Scope

- New validation rules.
- New validation rule syntax.
- New validation facade methods.
- `Request::safe()`, validated-data containers, or key-specific
  `validated('key')` access.
- `validateWithBag()` or multiple request error bags.
- FormRequest authorization changes.
- FormRequest failure response changes.
- Exception handler response shape changes.
- Session flashing changes.
- Controller base class helpers.

## Current Behavior

`FormRequest` already provides a full validation lifecycle:

1. `authorize()`
2. `prepareForValidation()`
3. `rules()`
4. `ValidationManager::validate()`
5. after callbacks
6. `failedValidation()`
7. `passedValidation()`
8. `validated()`

Plain `Request` already exposes rich input access through `all()`, `input()`,
`query()`, `post()`, JSON body parsing, typed casting, route params, and runtime
context. It does not currently expose a direct validation API.

`ValidationManager` already supports custom messages, attribute aliases,
`validateOrFail()`, and retrieving validated data. `ExceptionHandler` already
renders `ValidationException` as a JSON 422 response for JSON/AJAX requests or
as a redirect for web requests.

## Design

Add a small validation surface directly to `Request`.

For simple controller and closure actions, developers should be able to write:

```php
$validated = $request->validate([
    'email' => 'required|email',
    'name' => 'required|string|max:255',
]);
```

The method returns only data for fields declared in the rules and stores the
same array for later access through:

```php
$validated = $request->validated();
```

The ordinary `Request` API should not gain FormRequest lifecycle hooks. If an
action needs authorization, preparation hooks, after callbacks, or custom
failure hooks, it should continue using a `FormRequest` subclass.

## API Contract

### `Request::validate()`

Signature:

```php
public function validate(array $rules, array $messages = [], array $attributes = []): array
```

Behavior:

1. Validate `$this->all()` against `$rules`.
2. Apply `$messages` through `ValidationManager::setCustomMessages()` when the
   array is not empty.
3. Apply `$attributes` through `ValidationManager::setAliases()` when the array
   is not empty.
4. On success, store and return the validated data.
5. On failure, throw `ValidationException`.
6. Do not mutate request input.
7. Replace the previous stored validated data on each successful validation.
8. Leave previous stored validated data unchanged when a later validation fails.

The returned data follows existing `ValidationManager` semantics:

- top-level and dotted field names are accepted because `ValidationManager`
  already reads data through `data_get()`;
- optional fields that are absent are not included;
- fields not declared in `$rules` are not included;
- values are not cast or transformed by `Request::validate()`.

### `Request::validated()`

Signature:

```php
public function validated(): array
```

Behavior:

1. Return the most recent successful validation result for this `Request`.
2. Return an empty array when `validate()` has not succeeded yet.
3. Do not trigger validation by itself, because a plain `Request` does not own a
   rules definition.

This differs from `FormRequest::validated()`, which can trigger validation
because a `FormRequest` subclass owns `rules()`.

## Failure Behavior

`Request::validate()` should throw `ValidationException` with the same error
shape that `ValidationManager` and `FormRequest` already expose to
`ExceptionHandler`.

For HTTP rendering, no new behavior is added:

- JSON/AJAX requests continue to render a 422 JSON response through
  `ExceptionHandler`.
- Web requests continue to redirect back and flash errors through
  `ExceptionHandler`.

The request-level method only creates the exception; rendering remains the
exception handler's responsibility.

## Data Flow

1. A controller or closure receives a `Request`.
2. The action calls `$request->validate($rules, $messages, $attributes)`.
3. `Request` creates a `ValidationManager` with `$this->all()` and `$rules`.
4. Custom messages and attributes are applied.
5. The validator runs.
6. If validation fails, `Request` throws `ValidationException`.
7. If validation passes, `Request` stores and returns the validated array.
8. Later code can call `$request->validated()` to retrieve the stored result.

## Compatibility

- Existing `Request` input accessors remain unchanged.
- Existing `FormRequest` validation lifecycle remains unchanged.
- Existing `ValidationManager` public API remains unchanged.
- Existing `ExceptionHandler` rendering behavior remains unchanged.
- Existing controller dispatch and FormRequest injection behavior remains
  unchanged.
- Existing tests for validation, request input, FormRequest, dispatcher, and
  exception handling should continue passing.

## Error Handling

- Missing required fields produce validation errors through existing
  `ValidationManager` rules.
- Custom messages override default validation messages through existing
  `ValidationManager` behavior.
- Attribute aliases replace `:attribute` in validation messages through existing
  `ValidationManager` behavior.
- Invalid rules keep the existing `ValidationManager` behavior; this phase does
  not add unknown-rule exceptions.

## Testing

Add or extend focused tests:

- `Request::validate()` returns validated data for valid input.
- `Request::validate()` excludes fields not listed in rules.
- `Request::validate()` supports JSON request bodies through `Request::all()`.
- `Request::validate()` throws `ValidationException` for invalid input.
- Failed validation exposes the expected errors.
- Custom messages are applied.
- Attribute aliases are applied.
- `Request::validated()` returns an empty array before successful validation.
- `Request::validated()` returns the latest successful validation result.
- A failed validation does not replace the last successful validated data.
- Existing `FormRequest::validated()` behavior remains compatible.
- Existing `ValidationManager` tests continue passing.
- Existing dispatcher FormRequest integration tests continue passing.
- Existing exception handler validation rendering tests continue passing.

Focused verification:

```bash
php test tests/RequestTest.php
php test tests/FormRequestTest.php
php test tests/ValidationTest.php
php test tests/ValidationEngineTest.php
php test tests/DispatcherIntegrationTest.php
php test tests/ExceptionHandlerTest.php
```

Final verification:

```bash
php test
graphify update .
git status --short
```

## Acceptance Criteria

1. Plain `Request` instances expose `validate()` and `validated()`.
2. `Request::validate()` validates merged request input from query, post, JSON,
   and merged input.
3. `Request::validate()` returns only fields declared by validation rules.
4. `Request::validated()` returns the latest successful validation result and
   returns an empty array before validation succeeds.
5. Invalid input throws `ValidationException` with existing error structure.
6. Custom messages and attribute aliases are supported.
7. Existing `FormRequest` behavior remains unchanged.
8. Existing request, validation, FormRequest, dispatcher, and exception handler
   tests pass.
9. Full test suite passes after implementation.

## Follow-Up Phases

After this slice lands, remaining Phase 3 work can be scoped independently:

1. FormRequest failure customization or named error bags if concrete use cases
   justify them.
2. Narrow validation facade polish if existing facade annotations and runtime
   behavior need alignment.
3. Controller helper polish, such as a base controller, only if repeated
   controller patterns justify the additional API surface.
