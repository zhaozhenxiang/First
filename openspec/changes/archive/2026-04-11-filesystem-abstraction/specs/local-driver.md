# Spec: Local Filesystem Driver

## Interface: FilesystemAdapter

All drivers implement `Bin\Filesystem\FilesystemAdapter`.

### Operations
- `exists(path)` → bool
- `get(path)` → ?string (null if missing)
- `put(path, contents, options)` → bool (creates parent dirs)
- `append(path, data)` → bool
- `delete(path)` → bool (true for non-existent, false for dirs)
- `copy(from, to)` → bool
- `move(from, to)` → bool
- `size(path)` → int (0 if missing)
- `lastModified(path)` → int unix timestamp (0 if missing)
- `url(path)` → string
- `path(path)` → string (full absolute path)
- `makeDirectory(path)` → bool (idempotent)
- `deleteDirectory(path)` → bool (recursive)
- `files(directory)` → string[] (non-recursive)
- `allFiles(directory)` → string[] (recursive)
- `directories(directory)` → string[] (non-recursive)
- `allDirectories(directory)` → string[] (recursive)

## LocalDriver Behavior
- All paths resolved relative to configured root
- Path separators normalized to platform DIRECTORY_SEPARATOR
- Empty path maps to root directory itself
- URL: uses urlPrefix if set, otherwise `/path`
