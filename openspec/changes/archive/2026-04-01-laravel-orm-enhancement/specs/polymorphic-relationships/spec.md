## ADDED Requirements

### Requirement: MorphOne 多态一对一关系
模型 SHALL 支持 `morphOne(string $related, string $name, string $type = null, string $id = null, string $localKey = null)` 方法定义多态一对一关系。关联表 SHALL 使用 `{name}_type` 和 `{name}_id` 列标识父模型。

#### Scenario: 文章拥有一个图片
- **WHEN** Image 模型定义了多态关系，Post 模型定义了 `morphOne(Image::class, 'imageable')`
- **THEN** `$post->image` 返回 `imageable_type = 'Post'` 且 `imageable_id = $post->id` 的 Image 实例

### Requirement: MorphMany 多态一对多关系
模型 SHALL 支持 `morphMany(string $related, string $name, string $type = null, string $id = null, string $localKey = null)` 方法定义多态一对多关系。

#### Scenario: 文章拥有多条评论
- **WHEN** Comment 模型定义了多态关系，Post 模型定义了 `morphMany(Comment::class, 'commentable')`
- **THEN** `$post->comments` 返回该文章所有评论的 Collection
- **THEN** 每条评论的 `commentable_type` 为 Post 类名

### Requirement: MorphToMany 多态多对多关系
模型 SHALL 支持 `morphToMany(string $related, string $name, string $table = null, string $foreignPivotKey = null, string $relatedPivotKey = null, string $parentKey = null, string $relatedKey = null)` 方法定义多态多对多关系。中间表 SHALL 使用 `{name}_type` 和 `{name}_id` 标识。

#### Scenario: 文章拥有多个标签
- **WHEN** Post 模型定义了 `morphToMany(Tag::class, 'taggable')`
- **THEN** 中间表 `taggables` 包含 `taggable_type`、`taggable_id`、`tag_id` 列
- **THEN** `$post->tags` 返回关联的所有 Tag 模型

### Requirement: MorphByMany 反向多态多对多关系
模型 SHALL 支持 `morphByMany(string $related, string $name, ...)` 方法定义多态多对多的反向关系。

#### Scenario: 标签关联的文章
- **WHEN** Tag 模型定义了 `morphedByMany(Post::class, 'taggable')`
- **THEN** `$tag->posts` 返回所有关联该标签的文章

### Requirement: 多态关系的 eager loading
多态关系 SHALL 支持通过 `with()` 进行渴望加载。

#### Scenario: 加载多态关系
- **WHEN** 调用 `Post::with('comments', 'image')->get()`
- **THEN** 每个 Post 模型的 `comments` 和 `image` 关系已被加载，不产生额外查询
