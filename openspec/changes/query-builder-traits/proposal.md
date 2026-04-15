# QueryBuilder Trait 拆分

## 动机

`QueryBuilder.php` 单文件 2000 行，是项目中最大的类。内部职责边界清晰（WHERE 构建、关联加载、分页、分块），但全部混在一个类里。Model 基类已经用 5 个 trait 成功组织了类似规模的代码（665 行 + 5 个 trait 共 ~1160 行），QueryBuilder 应该采用相同模式。

## 范围

将 `bin/Database/QueryBuilder.php`（2000 行）拆分为：

| 文件 | 行数（估） | 职责 |
|------|-----------|------|
| `QueryBuilder.php`（主类） | ~400 | 属性、构造、CRUD、get/first/find、聚合、事务、工具方法 |
| `QueryBuilder/BuildsWhereClauses.php` | ~300 | 所有 where* 方法 + when/unless |
| `QueryBuilder/BuildsRelationships.php` | ~400 | eager loading、whereHas、withAggregate |
| `QueryBuilder/PaginatesResults.php` | ~150 | 三种分页 + 页码解析 |
| `QueryBuilder/ChunksResults.php` | ~80 | chunk/chunkById/each/eachById |

已有 `QueryBuilder/CompilesQueries.php`（252 行）保持不变。

## 设计决策

1. **所有属性留在主类** — PHP trait 不能与主类声明同名属性，集中管理更清晰
2. **聚合方法留在主类** — count/max/min/avg/sum 直接调用 get()，紧耦合核心执行管道
3. **whereHas 放在 BuildsRelationships** — 语义是"基于关联加条件"，虽然底层写 $wheres
4. **测试零改动** — trait 方法挂载后外部 API 不变

## 不变

- 公开 API 签名全部不变
- CompilesQueries trait 不动
- 测试文件不改

## 风险

- 低风险：纯代码搬迁，无逻辑变更，每步 `php -l` 验证语法
- 已有 1085 个测试覆盖，搬迁后全量跑测试验证
