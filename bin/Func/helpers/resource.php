<?php

declare(strict_types=1);

if (!function_exists('resource')) {
    /**
     * 创建 JSON 资源
     */
    function resource(mixed $data): mixed
    {
        return $data;
    }
}

if (!function_exists('resource_collection')) {
    /**
     * 创建资源集合
     */
    function resource_collection(mixed $data, ?string $resourceClass = null): \Bin\Resource\ResourceCollection
    {
        if ($resourceClass !== null) {
            return \Bin\Resource\ResourceCollection::make($data, $resourceClass);
        }

        return \Bin\Resource\AnonymousResourceCollection::make($data);
    }
}

if (!function_exists('json_resource')) {
    /**
     * 创建 JSON 资源并返回响应
     */
    function json_resource(mixed $data, int $status = 200): \Bin\Response\Response
    {
        if ($data instanceof \Bin\Resource\JsonResource) {
            return $data->toResponse($status);
        }

        return response($data, $status);
    }
}

if (!function_exists('paginate')) {
    /**
     * 创建分页资源集合
     */
    function paginate(
        mixed $data,
        int $total,
        int $perPage,
        int $currentPage,
        ?string $resourceClass = null
    ): \Bin\Resource\ResourceCollection {
        $collection = $resourceClass !== null
            ? \Bin\Resource\ResourceCollection::make($data, $resourceClass)
            : \Bin\Resource\AnonymousResourceCollection::make($data);

        $lastPage = (int) ceil($total / $perPage);
        $from = ($currentPage - 1) * $perPage + 1;
        $to = min($currentPage * $perPage, $total);

        return $collection->pagination([
            'total' => $total,
            'count' => is_countable($data) ? count($data) : 0,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $lastPage,
            'from' => $total > 0 ? $from : 0,
            'to' => $to,
        ]);
    }
}

if (!function_exists('uploaded_file')) {
    /**
     * 获取上传文件实例
     */
    function uploaded_file(string $key): ?\Bin\Http\UploadedFile
    {
        return \Bin\Http\UploadedFile::createFromGlobal($key);
    }
}

if (!function_exists('file_upload_validate')) {
    /**
     * 验证上传文件
     */
    function file_upload_validate(\Bin\Http\UploadedFile $file, array $rules): array
    {
        return $file->validate($rules);
    }
}

if (!function_exists('create_paginator')) {
    /**
     * 创建分页器
     */
    function create_paginator(array $items, int $total, int $perPage = 15, int $currentPage = 1, array $options = []): \Bin\Database\LengthAwarePaginator
    {
        return new \Bin\Database\LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);
    }
}

if (!function_exists('simple_paginator')) {
    /**
     * 创建简单分页器
     */
    function simple_paginator(array $items, int $perPage = 15, int $currentPage = 1, array $options = [], bool $hasMore = false): \Bin\Database\Paginator
    {
        return new \Bin\Database\Paginator($items, $perPage, $currentPage, $options, $hasMore);
    }
}

if (!function_exists('cursor_paginator')) {
    /**
     * 创建游标分页器
     */
    function cursor_paginator(array $items, int $perPage = 15, ?string $cursor = null, ?string $nextCursor = null, array $options = []): \Bin\Database\CursorPaginator
    {
        return new \Bin\Database\CursorPaginator($items, $perPage, $cursor, $nextCursor, $options);
    }
}

if (!function_exists('current_page')) {
    /**
     * 获取当前页码
     */
    function current_page(string $pageName = 'page', int $default = 1): int
    {
        $page = $_GET[$pageName] ?? $default;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }
}

if (!function_exists('per_page')) {
    /**
     * 获取每页数量
     */
    function per_page(string $paramName = 'per_page', int $default = 15, int $max = 100): int
    {
        $perPage = $_GET[$paramName] ?? $default;

        if (filter_var($perPage, FILTER_VALIDATE_INT) !== false) {
            $perPage = (int) $perPage;
            return min(max($perPage, 1), $max);
        }

        return $default;
    }
}
