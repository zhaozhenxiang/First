## Why

框架仅有基本的文件上传处理 (`UploadedFile`)，没有文件系统抽象。Laravel Filesystem 提供 Storage facade 和磁盘驱动 (local/s3/ftp/sftp)，统一文件读写接口。这是文件存储、日志管理、上传管理的基础。

## What Changes

- 新增 Filesystem 磁盘抽象：Storage Manager + Disk Driver
- 支持 3 种驱动：local / ftp / s3 (通过适配器)
- 统一 API：get/put/exists/delete/copy/move/size/lastModified/url/path
- 支持文件上传 -> store() / storeAs() / storePublicly()
- 支持目录操作：makeDirectory/deleteDirectory/files/directories/allFiles
- 新增 `config/filesystems.php` 配置文件
- 新增 `Storage` Facade

## Capabilities

### New Capabilities
- `filesystem-core`: StorageManager + FilesystemAdapter + 磁盘驱动
- `filesystem-local`: 本地文件系统驱动
- `filesystem-ftp`: FTP 驱动 (可选)
- `filesystem-s3`: S3 兼容驱动 (可选)
- `filesystem-config`: filesystems 配置文件

### Modified Capabilities

## Impact

- `bin/Filesystem/StorageManager.php` — 新增
- `bin/Filesystem/Filesystem.php` — 新增
- `bin/Filesystem/FilesystemAdapter.php` — 新增
- `bin/Filesystem/Drivers/LocalDriver.php` — 新增
- `bin/Filesystem/Drivers/FtpDriver.php` — 新增
- `bin/Filesystem/Drivers/S3Driver.php` — 新增
- `config/filesystems.php` — 新增
- `bin/Facade/Storage.php` — 新增
- `bin/Http/UploadedFile.php` — 新增 store/storeAs 方法
- `tests/FilesystemTest.php` — 新增测试
