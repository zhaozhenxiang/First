<?php

declare(strict_types=1);

namespace Tests;

use Bin\Auth\Gate;
use Bin\Auth\Policy;
use Bin\Auth\Rbac;
use Bin\Auth\AuthManager;
use Bin\Testing\TestCase;

/**
 * Authorization 测试
 */
class AuthorizationTest extends TestCase
{
    private ?string $originalProvider = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->originalProvider = config('auth.provider');
        } catch (\Exception $e) {
            $this->originalProvider = 'App\\Model\\User';
        }

        AuthManager::setProvider(TestUser2::class);
        Gate::clear();
        Rbac::clear();
        session_manager()->clear();
        AuthManager::resetUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->originalProvider !== null) {
            AuthManager::setProvider($this->originalProvider);
        }
        Gate::clear();
        Rbac::clear();
        session_manager()->clear();
        AuthManager::resetUser();
    }

    // Gate 测试
    public function testGateDefineAndCheck(): void
    {
        Gate::define('create-post', function($user) {
            return true;
        });

        $this->assertTrue(Gate::check('create-post'));
    }

    public function testGateDenies(): void
    {
        Gate::define('delete-post', function($user) {
            return false;
        });

        $this->assertTrue(Gate::denies('delete-post'));
        $this->assertFalse(Gate::allows('delete-post'));
    }

    public function testGateWithArguments(): void
    {
        Gate::define('update-post', function($user, $post) {
            return $post->user_id === 1;
        });

        $post = new \stdClass();
        $post->user_id = 1;

        $this->assertTrue(Gate::check('update-post', $post));

        $post->user_id = 2;

        $this->assertFalse(Gate::check('update-post', $post));
    }

    public function testGateAny(): void
    {
        Gate::define('edit', fn() => false);
        Gate::define('publish', fn() => true);

        $this->assertTrue(Gate::any(['edit', 'publish']));
    }

    public function testGateAll(): void
    {
        Gate::define('edit', fn() => true);
        Gate::define('publish', fn() => true);

        $this->assertTrue(Gate::all(['edit', 'publish']));
    }

    public function testGateHas(): void
    {
        Gate::define('test-ability', fn() => true);

        $this->assertTrue(Gate::has('test-ability'));
        $this->assertFalse(Gate::has('non-existent'));
    }

    public function testGateAbilities(): void
    {
        Gate::define('ability1', fn() => true);
        Gate::define('ability2', fn() => false);

        $abilities = Gate::abilities();

        $this->assertArrayHasKey('ability1', $abilities);
        $this->assertArrayHasKey('ability2', $abilities);
    }

    public function testGateClear(): void
    {
        Gate::define('test', fn() => true);

        $this->assertTrue(Gate::has('test'));

        Gate::clear();

        $this->assertFalse(Gate::has('test'));
    }

    // Policy 测试
    public function testPolicyRegistration(): void
    {
        Gate::policy('stdClass', TestPolicy::class);

        $policies = Gate::policies();

        $this->assertArrayHasKey('stdClass', $policies);
    }

    public function testPolicyCheck(): void
    {
        Gate::policy(\stdClass::class, TestPolicy::class);

        $model = new \stdClass();
        $model->user_id = 1;

        // 设置当前用户
        $user = new TestUser2(1, 'Test', 'test@example.com');
        AuthManager::login($user);

        $this->assertTrue(Gate::check('view', $model));
    }

    public function testPolicyBeforeHook(): void
    {
        Gate::policy(\stdClass::class, TestPolicyWithBefore::class);

        $model = new \stdClass();

        $user = new TestUser2(1, 'Test', 'test@example.com');
        $user->role = 'admin'; // 设置为管理员
        AuthManager::login($user);

        // before 返回 true（管理员总是有权限），应该直接通过
        $this->assertTrue(Gate::check('any', $model));
    }

    // RBAC 测试
    public function testRbacDefineRole(): void
    {
        Rbac::defineRole('editor', ['create-post', 'edit-post']);

        $this->assertTrue(Rbac::roleHasPermission('editor', 'create-post'));
        $this->assertFalse(Rbac::roleHasPermission('editor', 'delete-post'));
    }

    public function testRbacWildcardPermission(): void
    {
        Rbac::defineRole('admin', ['*']);

        $this->assertTrue(Rbac::roleHasPermission('admin', 'anything'));
    }

    public function testRbacAssignRole(): void
    {
        Rbac::defineRole('editor', ['edit-post']);

        Rbac::assignRole(1, 'editor');

        $this->assertTrue(Rbac::hasRole(1, 'editor'));
        $this->assertFalse(Rbac::hasRole(2, 'editor'));
    }

    public function testRbacHasPermission(): void
    {
        Rbac::defineRole('editor', ['edit-post']);
        Rbac::assignRole(1, 'editor');

        $this->assertTrue(Rbac::hasPermission(1, 'edit-post'));
        $this->assertFalse(Rbac::hasPermission(1, 'delete-post'));
    }

    public function testRbacRemoveRole(): void
    {
        Rbac::defineRole('editor', ['edit-post']);
        Rbac::assignRole(1, 'editor');

        $this->assertTrue(Rbac::hasRole(1, 'editor'));

        Rbac::removeRole(1, 'editor');

        $this->assertFalse(Rbac::hasRole(1, 'editor'));
    }

    public function testRbacHasAnyRole(): void
    {
        Rbac::assignRole(1, 'editor');
        Rbac::assignRole(1, 'reviewer');

        $this->assertTrue(Rbac::hasAnyRole(1, ['editor', 'admin']));
        $this->assertFalse(Rbac::hasAnyRole(1, ['admin', 'moderator']));
    }

    public function testRbacHasAllRoles(): void
    {
        Rbac::assignRole(1, 'editor');
        Rbac::assignRole(1, 'reviewer');

        $this->assertTrue(Rbac::hasAllRoles(1, ['editor', 'reviewer']));
        $this->assertFalse(Rbac::hasAllRoles(1, ['editor', 'admin']));
    }

    public function testRbacInheritance(): void
    {
        Rbac::defineRole('admin', ['*']);
        Rbac::defineRole('moderator', ['moderate-posts']);
        Rbac::setInheritance('moderator', 'admin');

        // moderator 继承 admin 的所有权限
        $this->assertTrue(Rbac::roleHasPermission('moderator', 'delete-posts'));
    }

    public function testRbacAddPermissionToRole(): void
    {
        Rbac::defineRole('editor', ['edit-post']);

        Rbac::addPermissionToRole('editor', 'delete-post');

        $this->assertTrue(Rbac::roleHasPermission('editor', 'delete-post'));
    }

    public function testRbacRemovePermissionFromRole(): void
    {
        Rbac::defineRole('editor', ['edit-post', 'delete-post']);

        Rbac::removePermissionFromRole('editor', 'delete-post');

        $this->assertFalse(Rbac::roleHasPermission('editor', 'delete-post'));
    }

    public function testRbacUserHasPermission(): void
    {
        Rbac::defineRole('editor', ['edit-post']);
        Rbac::assignRole(1, 'editor');

        $user = new TestUser2(1, 'Test', 'test@example.com');

        $this->assertTrue(Rbac::userHasPermission($user, 'edit-post'));
    }

    public function testRbacUserHasRole(): void
    {
        Rbac::assignRole(1, 'editor');

        $user = new TestUser2(1, 'Test', 'test@example.com');

        $this->assertTrue(Rbac::userHasRole($user, 'editor'));
    }

    public function testRbacClear(): void
    {
        Rbac::defineRole('editor', ['edit-post']);

        $this->assertNotEmpty(Rbac::roles());

        Rbac::clear();

        $this->assertEmpty(Rbac::roles());
    }

    public function testRbacGetUserRoles(): void
    {
        Rbac::assignRole(1, 'editor');
        Rbac::assignRole(1, 'reviewer');

        $roles = Rbac::getUserRoles(1);

        $this->assertContains('editor', $roles);
        $this->assertContains('reviewer', $roles);
    }

    public function testRbacGetRolePermissions(): void
    {
        Rbac::defineRole('editor', ['edit-post', 'publish-post']);

        $permissions = Rbac::getRolePermissions('editor');

        $this->assertContains('edit-post', $permissions);
        $this->assertContains('publish-post', $permissions);
    }
}

/**
 * 测试 Policy
 */
class TestPolicy extends Policy
{
    public function view(?object $user, object $model): bool
    {
        return $user !== null && $model->user_id === $user->id;
    }

    public function update(?object $user, object $model): bool
    {
        return $this->isOwner($user, $model);
    }

    public function delete(?object $user, object $model): bool
    {
        return $this->hasAdminRole($user);
    }
}

/**
 * 带 before 钩子的测试 Policy
 */
class TestPolicyWithBefore extends Policy
{
    public function before(?object $user, string $ability, object $model): ?bool
    {
        // 管理员总是有权限
        if ($this->hasAdminRole($user)) {
            return true;
        }

        return null; // 继续检查
    }

    public function any(?object $user, object $model): bool
    {
        return false;
    }
}

/**
 * 测试用户模型 2
 */
class TestUser2
{
    public int $id;
    public string $name;
    public string $email;
    public string $password;
    public string $role = 'user';

    private static array $users = [];

    public function __construct(int $id, string $name, string $email, string $password = 'secret123')
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = \Bin\Auth\HashManager::make($password);

        self::$users[$id] = $this;
    }

    public function save(): void
    {
        self::$users[$this->id] = $this;
    }

    public static function find(int $id): ?self
    {
        return self::$users[$id] ?? null;
    }

    public static function where(string $column, string $value): self
    {
        return new self(0, '', '');
    }

    public function orWhere(string $column, string $value): self
    {
        foreach (self::$users as $user) {
            if (isset($user->$column) && $user->$column === $value) {
                return $user;
            }
        }

        return new self(0, '', '');
    }

    public function first(): ?self
    {
        foreach (self::$users as $user) {
            if (!empty($user->email)) {
                return $user;
            }
        }

        return null;
    }

    public static function reset(): void
    {
        self::$users = [];
        new self(1, 'Test User', 'test@example.com');
    }
}

// 初始化测试用户
TestUser2::reset();
