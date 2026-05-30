# Developer API Controller Response Polish Design

Date: 2026-05-31
Status: Ready for implementation plan

## Goal

Make controller and closure return values normalize into predictable HTTP
responses, with JSON resources and resource collections producing the same
response shape whether returned directly from an action or converted through
`toResponse()`.

This is the first scoped slice of Follow-Up Phase 3 from
`docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`.
The broader phase covers request, response, validation, resources, and
controller helpers; this slice focuses only on response normalization for
controller and closure actions.

## Scope

### In Scope

- `ResponseFactory::make()` normalizes common controller return values.
- `ResponseFactory::json()` accepts any JSON-serializable payload that can be
  encoded by `json_encode()`.
- Controller methods and route closures can return:
  - `Response`
  - scalar values
  - arrays
  - `JsonSerializable` values
  - `Bin\Resource\JsonResource`
  - `Bin\Resource\ResourceCollection`
  - `null`
- `JsonResource::toResponse()` uses the same JSON response path as controller
  return normalization.
- `ResourceCollection::toResponse()` uses the same JSON response path as
  controller return normalization.
- Existing array, scalar, `Response`, `View`, and null behavior remains
  compatible.
- Focused tests cover the response factory, controller dispatch, route closure
  dispatch, and resource `toResponse()` behavior.

### Out of Scope

- `Request::validate()` or request validation helpers.
- FormRequest failure response changes.
- Validation facade expansion.
- A base controller class.
- Content negotiation beyond the existing JSON response behavior.
- Absolute URL or redirect helper changes beyond preserving current behavior.
- Rewriting `Response` into a PSR-7 implementation.
- Changing resource array shapes, pagination metadata, filtering, or includes.

## Current Behavior

`ControllerDispatcher` already delegates controller and closure return values
to `ResponseFactory::make()`. `HttpKernel` also wraps the routed result through
`ResponseFactory::make()`.

`ResponseFactory::make()` currently handles:

- existing `Response` instances
- scalar payloads
- array payloads with a JSON content type

`Response` itself encodes arrays and renders views, but it does not explicitly
handle `JsonSerializable` payloads. As a result, returning a `JsonResource` or
`ResourceCollection` from a controller depends on implicit object string
behavior rather than a clear response contract. The resource `toResponse()`
methods also construct `Response` directly, bypassing the factory boundary.

## Design

Keep response normalization centralized in `ResponseFactory`. Callers that need
an HTTP response should ask the factory to build it instead of duplicating
payload-type checks.

`ControllerDispatcher` should continue to:

1. Resolve controller or closure parameters.
2. Invoke the action through the container.
3. Pass the raw action result to `ResponseFactory::make()`.

`HttpKernel` should continue to call `ResponseFactory::make()` around the routed
result. If the dispatcher already returned a `Response`, the factory pass-through
keeps this idempotent.

`JsonResource::toResponse()` and `ResourceCollection::toResponse()` should use
the response factory's JSON path so they match action return behavior.

## Response Normalization Contract

`ResponseFactory::make(mixed $payload = '', int $status = 200, array $headers = []): Response`
should follow these rules:

1. If `$payload` is a `Response`, return it after applying supplied headers.
2. If `$payload` is an array, return a JSON response with
   `Content-Type: application/json` unless the caller supplied a content type.
3. If `$payload` is `JsonSerializable`, return a JSON response with
   `Content-Type: application/json` unless the caller supplied a content type.
4. If `$payload` is scalar, cast it to string and return a normal response.
5. If `$payload` is `null`, return an empty response.
6. Otherwise, preserve current `Response` constructor behavior by passing the
   payload through unchanged.

`ResponseFactory::json(mixed $payload, int $status = 200, array $headers = []): Response`
should:

1. Accept arrays and `JsonSerializable` payloads.
2. Preserve the caller-supplied status code.
3. Set `Content-Type: application/json` by default.
4. Let `json_encode(..., JSON_THROW_ON_ERROR)` failures propagate, matching the
   current array response behavior.

Header precedence should remain compatible with the current factory behavior:
default JSON headers are added first, and caller-supplied headers can override
them when needed.

## Resource Response Contract

`JsonResource::toResponse(int $status = 200): Response` should return the same
JSON body and JSON content type that a controller would produce when returning
the resource directly.

`ResourceCollection::toResponse(int $status = 200): Response` should return the
same JSON body and JSON content type that a controller or closure would produce
when returning the collection directly.

Resource serialization remains unchanged:

- `JsonResource` serializes to its filtered resource array.
- `ResourceCollection` serializes to a top-level `data` key plus any existing
  metadata.
- Pagination metadata remains under `meta.pagination`.

## Data Flow

### Controller Method Return

1. Route dispatch resolves the controller action.
2. `ControllerDispatcher::dispatch()` invokes the action through the container.
3. The raw return value is passed to `ResponseFactory::make()`.
4. The factory returns a `Response`.
5. `HttpKernel` may pass that response through the factory again, which returns
   the same response instance.

### Closure Return

1. Route dispatch resolves the closure action.
2. `ControllerDispatcher::dispatchClosure()` invokes the closure through the
   container.
3. The raw return value is passed to `ResponseFactory::make()`.
4. The factory returns a `Response`.

### Resource `toResponse()`

1. Application code calls `$resource->toResponse($status)`.
2. The resource asks the application for `ResponseFactory`.
3. The factory builds a JSON response from the resource itself.
4. The response body matches `json_encode($resource, JSON_THROW_ON_ERROR)`.

## Compatibility

- Existing controller tests that assert string and array response behavior
  remain valid.
- Returning a `Response` from a controller still preserves the response
  instance.
- Existing `response()` and `redirect()` helpers continue to use
  `ResponseFactory`.
- Existing `Response` construction with strings, arrays, views, and null keeps
  working.
- Existing resource filtering, includes, hidden fields, visible fields, and
  pagination behavior is not changed.
- Existing exception behavior for invalid JSON payloads is not hidden.

## Error Handling

- Invalid JSON serialization throws the same JSON exception family currently
  thrown for array response encoding.
- Missing application bindings for `ResponseFactory` should continue to
  propagate from helper usage and controller dispatch.
- `ResponseFactory::make()` should not swallow exceptions from resource
  serialization.

## Testing

Add or extend focused tests:

- `ResponseFactory::make()` turns `JsonSerializable` payloads into JSON
  responses with `Content-Type: application/json`.
- `ResponseFactory::json()` accepts `JsonSerializable` payloads.
- `JsonResource::toResponse()` returns a JSON response with encoded resource
  data and a JSON content type.
- `ResourceCollection::toResponse()` returns a JSON response with the existing
  collection envelope and a JSON content type.
- A controller returning `JsonResource` produces the same response body and
  content type as `JsonResource::toResponse()`.
- A closure returning `ResourceCollection` produces the same response body and
  content type as `ResourceCollection::toResponse()`.
- Returning an existing `Response` from a controller still passes through
  unchanged.
- Returning an array from a controller still produces JSON.
- Returning a scalar from a controller still produces a string body.

Focused verification:

```bash
php test tests/ResponseTest.php
php test tests/ResourceTest.php
php test tests/DispatcherIntegrationTest.php
```

Final verification:

```bash
php test
graphify update .
git status --short
```

## Acceptance Criteria

1. Controller methods can return `JsonResource` and receive a JSON `Response`.
2. Route closures can return `ResourceCollection` and receive a JSON `Response`.
3. `JsonResource::toResponse()` emits a JSON body and JSON content type.
4. `ResourceCollection::toResponse()` emits a JSON body and JSON content type.
5. `ResponseFactory::make()` handles `JsonSerializable` payloads through the
   JSON response path.
6. Existing array, scalar, `Response`, view, null, and redirect response
   behavior remains compatible.
7. Focused response, resource, and dispatcher tests pass.
8. Full test suite passes after implementation.

## Follow-Up Phases

After this slice lands, the remaining Phase 3 work can be scoped independently:

1. Request and validation polish: request-level validation helpers, validated
   data access, and FormRequest failure behavior.
2. Controller helper polish: base controller conveniences only if repeated
   controller patterns justify them.
3. Additional facade polish: narrow facade additions backed by existing service
   APIs and tests.
