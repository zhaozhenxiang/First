## ADDED Requirements

### Requirement: HasOneThrough 远层一对一关系
模型 SHALL 支持 `hasOneThrough(string $related, string $through, string $firstKey = null, string $secondKey = null, string $localKey = null, string $secondLocalKey = null)` 关系定义，通过中间模型建立远层一对一关系。

#### Scenario: 用户通过用户资料获取地址
- **WHEN** User 模型定义了 `hasOneThrough(Address::class, UserProfile::class)`
- **THEN** 通过一次查询（JOIN 中间表）获取用户的 Address
- **THEN** SQL 为 `SELECT addresses.* FROM addresses JOIN user_profiles ON addresses.user_profile_id = user_profiles.id WHERE user_profiles.user_id = ?`

#### Scenario: 自定义外键
- **WHEN** 指定了自定义的 `firstKey`、`secondKey` 等参数
- **THEN** JOIN 和 WHERE 条件使用指定的键名

### Requirement: HasManyThrough 远层一对多关系
模型 SHALL 支持 `hasManyThrough(string $related, string $through, string $firstKey = null, string $secondKey = null, string $localKey = null, string $secondLocalKey = null)` 关系定义，通过中间模型建立远层一对多关系。

#### Scenario: 国家通过用户获取文章
- **WHEN** Country 模型定义了 `hasManyThrough(Post::class, User::class)`
- **THEN** 获取该国家所有用户的所有文章
- **THEN** SQL 为 `SELECT posts.* FROM posts JOIN users ON posts.user_id = users.id WHERE users.country_id = ?`

#### Scenario: 返回 Collection
- **WHEN** 调用 `$country->posts`
- **THEN** 返回 Collection 实例，包含多个 Post 模型
