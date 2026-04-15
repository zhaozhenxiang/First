# Tasks

## Task 1: 创建 BuildsWhereClauses trait
- 新建 `bin/Database/QueryBuilder/BuildsWhereClauses.php`
- 从 QueryBuilder 提取以下方法：
  - 公开: where, orWhere, whereIn, whereNotIn, whereNull, whereNotNull, whereBetween, whereLike, whereRaw, orWhereRaw, whereColumn, orWhereColumn, whereDate, whereDay, whereMonth, whereYear, whereTime, whereExists, whereNotExists, orWhereExists, whereNot, when, unless
  - 保护: whereNested, forNestedWhere, addNestedWhereQuery, not
- 不声明属性（使用主类 $wheres, $bindings）
- 在 QueryBuilder 主类 use BuildsWhereClauses
- 删除主类中已提取的方法
- `php -l` 语法检查

## Task 2: 创建 BuildsRelationships trait
- 新建 `bin/Database/QueryBuilder/BuildsRelationships.php`
- 从 QueryBuilder 提取以下方法：
  - 公开: with, whereHas, whereDoesntHave, orWhereHas, orWhereDoesntHave, withCount, withSum, withAvg, withMin, withMax, withAggregate, getWithAggregates
  - 保护: eagerLoadRelations, eagerLoadRelationOne, eagerLoadRelationNested, hasInternal, applyWithAggregateSelects, hydrateWithAggregates
- 不声明属性（使用主类 $eagerLoads, $withAggregates, $modelClass）
- 在 QueryBuilder 主类 use BuildsRelationships
- 删除主类中已提取的方法
- `php -l` 语法检查

## Task 3: 创建 PaginatesResults trait
- 新建 `bin/Database/QueryBuilder/PaginatesResults.php`
- 从 QueryBuilder 提取以下方法：
  - 公开: paginate, simplePaginate, cursorPaginate, forPage
  - 保护: getCountForPagination, resolveCurrentPage, resolvePath, resolveQuery
  - 静态: setPageResolver, disablePageResolver
- 不声明属性（使用主类 $pageResolver）
- 在 QueryBuilder 主类 use PaginatesResults
- 删除主类中已提取的方法
- `php -l` 语法检查

## Task 4: 创建 ChunksResults trait
- 新建 `bin/Database/QueryBuilder/ChunksResults.php`
- 从 QueryBuilder 提取以下方法：
  - 公开: chunk, chunkById, each, eachById
- 在 QueryBuilder 主类 use ChunksResults
- 删除主类中已提取的方法
- `php -l` 语法检查

## Task 5: 全量验证
- `php -l bin/Database/QueryBuilder.php` + 所有新 trait 文件
- `php test` 运行全部 1085 个测试
- 确认零失败
