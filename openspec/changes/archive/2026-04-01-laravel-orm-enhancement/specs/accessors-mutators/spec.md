## ADDED Requirements

### Requirement: 属性访问器（Getter）
模型 SHALL 支持通过 `getFooAttribute()` 方法定义属性访问器。当通过 `$model->foo` 或 `$model->getAttribute('foo')` 访问属性时，若存在 `getFooAttribute()` 方法，SHALL 调用该方法返回值，而不是直接返回原始属性值。

#### Scenario: 通过命名约定定义访问器
- **WHEN** 模型定义了 `getNameAttribute()` 方法返回 `ucfirst($this->attributes['name'])`
- **THEN** `$model->name` 返回首字母大写的名称
- **THEN** `$model->getOriginal('name')` 返回原始值

#### Scenario: 访问器不存在时返回原始值
- **WHEN** 模型未定义 `getEmailAttribute()` 方法
- **THEN** `$model->email` 直接返回 `$this->attributes['email']`

### Requirement: 属性修改器（Setter）
模型 SHALL 支持通过 `setFooAttribute()` 方法定义属性修改器。当通过 `$model->foo = value` 或 `$model->setAttribute('foo', value)` 设置属性时，若存在 `setFooAttribute()` 方法，SHALL 将值传入该方法处理。

#### Scenario: 通过命名约定定义修改器
- **WHEN** 模型定义了 `setNameAttribute($value)` 方法将值转为小写存储
- **THEN** `$model->name = 'HELLO'` 后 `$model->getOriginal()` 中 name 为 'hello'

#### Scenario: 修改器不存在时直接赋值
- **WHEN** 模型未定义 `setEmailAttribute()` 方法
- **THEN** `$model->email = 'test@test.com'` 直接存储到 `$this->attributes['email']`

### Requirement: Attribute 类定义访问器/修改器
模型 SHALL 支持通过返回 `Attribute` 对象的方法定义访问器和修改器。方法名使用驼峰命名（如 `name()` 返回 Attribute 对象）。

#### Scenario: 使用 Attribute 类同时定义 get 和 set
- **WHEN** 模型定义了 `protected function name(): Attribute` 方法，返回 `new Attribute(get: fn($v) => ucfirst($v), set: fn($v) => strtolower($v))`
- **THEN** 读取时返回首字母大写值
- **THEN** 写入时转为小写存储

### Requirement: 计算属性追加（Appends）
模型 SHALL 支持通过 `$appends` 属性和 `append()` 方法将计算属性追加到 `toArray()` 和 `toJson()` 输出中。

#### Scenario: 通过 $appends 属性追加
- **WHEN** 模型定义了 `protected array $appends = ['full_name']` 和 `getFullNameAttribute()` 访问器
- **THEN** `$model->toArray()` 包含 `full_name` 键及其访问器返回值

#### Scenario: 通过 append() 方法动态追加
- **WHEN** 调用 `$model->append('avatar_url')`
- **THEN** `$model->toArray()` 包含 `avatar_url` 键
