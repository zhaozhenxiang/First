# Spec: Storage Manager

## StorageManager

### Singleton
- `getInstance()` → static instance
- `resetInstance()` → clear singleton (testing)

### Configuration
- Reads `config('filesystems.disks')` and `config('filesystems.default')` at construction
- `setConfig(array)` and `setDefaultDisk(string)` for programmatic config

### Disk Resolution
- `disk(?name)` → Filesystem (lazy, cached)
- Unconfigured disk → RuntimeException
- Unsupported driver → RuntimeException
- `flush()` → clear all resolved disks

### Proxy Methods
- All Filesystem methods proxied to default disk:
  exists, get, put, delete, copy, move, url, path, size, lastModified, files, allFiles, makeDirectory, deleteDirectory, append, prepend

## Storage Facade
- `Bin\Facade\Storage` → static proxy to `StorageManager::getInstance()`
- All StorageManager proxy methods available as static calls
