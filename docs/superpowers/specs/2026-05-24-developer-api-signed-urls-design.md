# Developer API Signed URLs Design

Date: 2026-05-24
Status: Ready for implementation plan

## Goal

Add Laravel-style signed URL generation and validation to First's developer API
without widening the routing surface beyond the existing relative named-route URL
generation model.

This is Follow-Up Phase 2 from
`docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`.
Production routing tools are already merged into `master`; this phase adds
signed routes, temporary signatures, request validation, and a `signed`
middleware alias.

## Scope

### In Scope

- `URL::signedRoute(string $name, array $params = []): string`
- `URL::temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $params = []): string`
- `Request::hasValidSignature(): bool`
- `Bin\Middleware\ValidateSignature`
- Default middleware alias: `signed`
- HMAC-SHA256 signatures based on a canonical relative URL.
- Signing key source: `config('app.key')`.
- Support for raw keys and `base64:` encoded keys.
- Deterministic query string ordering before signing.
- Expiration via an `expires` Unix timestamp query parameter.
- Tamper detection for path, query values, missing signatures, malformed
  expiration values, and expired signatures.

### Out of Scope

- Absolute URL signatures bound to scheme and host.
- Ignoring arbitrary query keys during validation.
- Key rotation.
- Global helper functions such as `signed_route()`.
- Route cache format changes.
- Controller, request, response, validation, or resource API polish.

## API Contract

`URL::signedRoute()` generates a URL for an existing named route and appends a
`signature` query parameter:

```php
Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

$url = URL::signedRoute('invites.show', [
    'invite' => 42,
    'email' => 'taylor@example.com',
]);
```

The output is a relative URL:

```text
/invites/42?email=taylor%40example.com&signature=<hmac>
```

`URL::temporarySignedRoute()` adds an `expires` timestamp before signing:

```php
$url = URL::temporarySignedRoute('invites.show', time() + 3600, [
    'invite' => 42,
]);
```

The output is:

```text
/invites/42?expires=<timestamp>&signature=<hmac>
```

`Request::hasValidSignature()` returns `true` only when the request path and
query string match the supplied signature and the optional expiration timestamp
has not passed.

`ValidateSignature` aborts invalid requests with HTTP 403. It is exposed through
the default middleware alias:

```php
Route::get('/invites/{invite}', 'InviteController@show')
    ->middleware('signed')
    ->name('invites.show');
```

## Signature Algorithm

Sign the relative path plus canonical query string. Do not include scheme or
host.

Canonicalization rules:

1. Generate the base route URL with `RouteCollection::url()`.
2. Parse the generated URL into path and query components.
3. Reject user parameters containing reserved keys: `signature` or `expires`.
4. Add `expires` for temporary signed routes.
5. Sort query keys recursively with `ksort()`.
6. Build the canonical string with `http_build_query()`.
7. Compute `hash_hmac('sha256', $canonicalUrl, $key)`.
8. Add `signature` to the query and render the final URL with the same sorting
   rules.

Example canonical string:

```text
/invites/42?email=taylor%40example.com
```

The final URL includes the signature as a normal sorted query key. For example,
`a`, `signature`, and `z` are rendered in that order.

## Key Handling

The signing key comes from `config('app.key')`.

- Generation throws `RuntimeException` when `app.key` is missing or empty.
- Validation returns `false` when the key is missing or empty.
- Values prefixed with `base64:` are decoded and used as raw HMAC keys when the
  base64 payload is valid.
- Invalid `base64:` payloads fall back to the original string value, preserving
  predictable behavior instead of silently using an empty key.

## Validation Behavior

`Request::hasValidSignature()` returns `false` when:

- `signature` is missing.
- `signature` is not a non-empty string.
- `expires` is present but is not an unsigned integer string.
- `expires` is older than the current Unix timestamp.
- The request path differs from the signed path.
- Any signed query parameter is added, removed, or changed.
- `config('app.key')` is missing or empty.

The `signature` parameter itself is excluded when recomputing the HMAC.

## Acceptance Criteria

1. Named routes can generate signed URLs with unused route parameters rendered
   as sorted query parameters.
2. Temporary signed routes include an `expires` timestamp and validate until the
   timestamp has passed.
3. Signed URL validation detects tampered path and query values.
4. Missing signatures, malformed expiration values, and expired URLs fail
   validation.
5. `ValidateSignature` allows valid signed requests and throws HTTP 403 for
   invalid signed requests.
6. The default middleware config exposes the `signed` alias.
7. Existing route URL generation, request, middleware, and lifecycle behavior
   remain compatible.

