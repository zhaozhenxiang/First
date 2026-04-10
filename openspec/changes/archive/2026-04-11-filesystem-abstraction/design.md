# Design: Filesystem Abstraction

## Architecture

```
StorageManager (singleton, config-driven)
  └── disk(name) → Filesystem
                      └── FilesystemAdapter (interface)
                            └── LocalDriver (local filesystem)
```

## Components

### FilesystemAdapter Interface
- Core CRUD: exists, get, put, append, delete, copy, move
- Metadata: size, lastModified, url, path
- Directory: makeDirectory, deleteDirectory, files, allFiles, directories, allDirectories

### LocalDriver
- Root-path based: all paths resolved relative to configured root
- URL prefix support for public disks
- Recursive file/directory enumeration
- Recursive directory deletion

### Filesystem (wrapper)
- Delegates all operations to adapter
- Adds: missing(), getOrElse(), prepend(), delete(array)
- Proxy pattern with getAdapter() accessor

### StorageManager
- Singleton pattern with resetInstance() for testing
- Config from config('filesystems.disks')
- Lazy disk resolution with caching
- Proxy methods for default disk operations
- Extensible via match expression for new drivers

### Storage Facade
- Static proxy to StorageManager::getInstance()
- Full @method annotations for IDE autocompletion

### Config
- config/filesystems.php: default disk, disks array
- Each disk: driver, root, url (optional)
