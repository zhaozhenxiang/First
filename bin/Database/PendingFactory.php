<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Database\Relations\BelongsTo;

/**
 * 工厂 DSL 待定代理
 *
 * Model::factory() 返回本类实例，流式配置后经 create()/make() 落地：
 *
 *   Post::factory()->count(3)->state('published')->create();
 *   Post::factory()->for($user)->create();
 *   User::factory()->has(Post::factory()->count(2))->create();
 *   Tag::factory()->sequence(['name' => 'a'], ['name' => 'b'])->createManyTimes();
 *
 * 定义解析顺序：Factory::define() 闭包 > Database\Factories\{类名}Factory::definition()
 */
class PendingFactory
{
    protected int $count = 1;

    /** @var list<string|\Closure> 命名状态（查注册表）或闭包状态（属性 => 属性） */
    protected array $states = [];

    /** @var list<array<string, mixed>> sequence 轮换属性组 */
    protected array $sequence = [];

    protected int $sequenceIndex = 0;

    /** @var list<array{parent: Model, relationship: string}> for() 声明的父模型 */
    protected array $parents = [];

    /** @var list<array{factory: PendingFactory|Model, relationship: ?string}> has() 声明的子工厂 */
    protected array $children = [];

    public function __construct(
        protected string $modelClass,
    ) {
    }

    /**
     * 设置创建数量（create 时返回 Collection）
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * count 的别名
     */
    public function times(int $count): self
    {
        return $this->count($count);
    }

    /**
     * 追加状态：命名状态查 Factory::state 注册表（或工厂类 states() 方法），闭包直接生效
     */
    public function state(string|\Closure $state): self
    {
        $this->states[] = $state;

        return $this;
    }

    /**
     * 属性轮换：第 n 个实例取第 n mod len 组属性
     */
    public function sequence(array ...$attributeSets): self
    {
        $this->sequence = $attributeSets;

        return $this;
    }

    /**
     * 归属父模型（BelongsTo 方向）：创建时按关系外键接线
     */
    public function for(Model $parent, ?string $relationship = null): self
    {
        $this->parents[] = ['parent' => $parent, 'relationship' => $relationship];

        return $this;
    }

    /**
     * 附带子工厂（HasOne/HasMany/morph 方向）：父模型创建后按关系创建子模型
     *
     * @param PendingFactory|Model $factory 子工厂（Model 视为 count=1、无状态的单体）
     * @param string|null $relationship 关系名；缺省按子模型表名约定猜测
     */
    public function has(PendingFactory|Model $factory, ?string $relationship = null): self
    {
        $this->children[] = ['factory' => $factory, 'relationship' => $relationship];

        return $this;
    }

    /**
     * 创建并保存（count > 1 时返回 Collection）
     */
    public function create(array $attributes = []): Model|Collection
    {
        if ($this->count === 1) {
            return $this->createOne($attributes);
        }

        $models = [];

        for ($i = 0; $i < $this->count; $i++) {
            $models[] = $this->createOne($attributes);
        }

        return new Collection($models);
    }

    /**
     * 实例化不保存（count > 1 时返回 Collection）
     */
    public function make(array $attributes = []): Model|Collection
    {
        if ($this->count === 1) {
            return $this->makeOne($attributes);
        }

        $models = [];

        for ($i = 0; $i < $this->count; $i++) {
            $models[] = $this->makeOne($attributes);
        }

        return new Collection($models);
    }

    /**
     * 创建单个实例
     */
    protected function createOne(array $attributes): Model
    {
        $model = $this->makeOne($attributes);

        $model->save();

        // has() 子工厂：父模型落库后按关系创建
        foreach ($this->children as $child) {
            $this->createChild($model, $child['factory'], $child['relationship']);
        }

        return $model;
    }

    /**
     * 实例化单个实例（definition + states + sequence + 显式属性 + for 父模型接线）
     */
    protected function makeOne(array $attributes): Model
    {
        $modelClass = $this->modelClass;

        $definition = Factory::resolveDefinition($modelClass);

        // 状态：命名状态查注册表/工厂类方法，闭包状态直接应用
        foreach ($this->states as $state) {
            $definition = array_merge(
                $definition,
                $this->resolveStateAttributes($state, $definition)
            );
        }

        // sequence 轮换
        if ($this->sequence !== []) {
            $definition = array_merge(
                $definition,
                $this->sequence[$this->sequenceIndex % count($this->sequence)]
            );
            $this->sequenceIndex++;
        }

        // for() 父模型：按关系外键接线
        foreach ($this->parents as $parentConfig) {
            $foreignKey = $this->guessForeignKey($parentConfig['parent'], $parentConfig['relationship']);

            $definition[$foreignKey] = $parentConfig['parent']->getKey();
        }

        return new $modelClass(array_merge($definition, $attributes));
    }

    /**
     * 创建 has() 声明的子模型
     */
    protected function createChild(Model $parent, PendingFactory|Model $factory, ?string $relationship): void
    {
        if ($factory instanceof Model) {
            $factory->setAttribute($this->guessForeignKey($parent, null), $parent->getKey());
            $factory->save();

            return;
        }

        $relation = $parent->{$relationship ?? $this->guessRelationshipName($parent, $factory->getModelClass())}();

        if (!method_exists($relation, 'save')) {
            throw new \RuntimeException('Relation [' . $relation::class . '] does not support factory has().');
        }

        $count = $factory instanceof PendingFactory ? $factory->count : 1;

        for ($i = 0; $i < $count; $i++) {
            $child = $factory instanceof PendingFactory ? $factory->makeOne([]) : $factory;

            $relation->save($child);

            // 子工厂自身的 has() 递归落地
            if ($factory instanceof PendingFactory) {
                $factory->resolveChildrenFor($child);
            }
        }
    }

    /**
     * 在指定父模型上落地待定的 children（供 has() 嵌套递归）
     */
    protected function resolveChildrenFor(Model $parent): void
    {
        foreach ($this->children as $child) {
            $this->createChild($parent, $child['factory'], $child['relationship']);
        }
    }

    /**
     * 解析状态属性
     *
     * @return array<string, mixed>
     */
    protected function resolveStateAttributes(string|\Closure $state, array $definition): array
    {
        if ($state instanceof \Closure) {
            $result = $state($definition);

            return is_array($result) ? $result : [];
        }

        // 命名状态：先查 Factory 注册表，再查工厂类的 states() 方法
        $registered = Factory::resolveNamedState($this->modelClass, $state, $definition);

        if ($registered !== null) {
            return $registered;
        }

        throw new \InvalidArgumentException("Unknown state [{$state}] for factory [{$this->modelClass}].");
    }

    /**
     * 猜测关系外键：关系名是子模型侧的 BelongsTo 关系。
     * 未显式给出时按父类名的 camel/snake 形式在子模型上找关系方法，
     * 找不到再退化为 snake(父类名)_id。
     */
    protected function guessForeignKey(Model $parent, ?string $relationship): string
    {
        $child = new $this->modelClass();

        $candidates = $relationship !== null
            ? [$relationship]
            : $this->parentNameVariants($parent::class);

        foreach ($candidates as $candidate) {
            if (!method_exists($child, $candidate)) {
                continue;
            }

            $relation = $child->{$candidate}();

            if ($relation instanceof BelongsTo) {
                return $relation->getForeignKey();
            }
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr(strrchr($parent::class, '\\'), 1))) . '_id';
    }

    /**
     * 父类名的 camel/snake 变体（子模型 belongsTo 关系命名约定）
     *
     * @return list<string>
     */
    protected function parentNameVariants(string $parentClass): array
    {
        $short = substr(strrchr($parentClass, '\\'), 1) ?: $parentClass;
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $short))));

        return array_values(array_unique([$camel, $snake]));
    }

    /**
     * 猜测父模型上的子关系名：复数类名 → 表名，取父模型上存在方法的候选
     */
    protected function guessRelationshipName(Model $parent, string $childClass): string
    {
        $table = (new $childClass())->getTable();
        $short = substr(strrchr($childClass, '\\'), 1);
        $snakePlural = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . 's';

        foreach ([$snakePlural, $table] as $candidate) {
            if (method_exists($parent, $candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException(
            "Cannot guess a relationship for [" . $childClass . "] on [" . $parent::class . "]; tried [{$snakePlural}, {$table}]. Pass the relationship name explicitly."
        );
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }
}
