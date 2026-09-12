<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 全局作用域接口
 *
 * 实现类在 apply() 中为查询添加约束，可经 #[ScopedBy] 属性
 * 或 boot 中 static::addGlobalScope(new XxxScope) 注册。
 */
interface Scope
{
    public function apply(QueryBuilder $query, Model $model): void;
}
