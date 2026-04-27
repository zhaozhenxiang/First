<?php

declare(strict_types=1);

namespace Tests;

use Bin\Auth\AuthManager;
use Bin\Auth\HashManager;
use Bin\Auth\PasswordResetManager;
use Bin\Request\Request;
use Bin\Session\SessionManager;
use Bin\Testing\TestCase;

/**
 * Auth 测试
 */
class AuthTest extends TestCase
{
    private ?string $originalProvider = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 保存原始提供者
        try {
            $this->originalProvider = config('auth.provider');
        } catch (\Exception $e) {
            $this->originalProvider = 'App\\Model\\User';
        }

        // 使用测试用户模型
        AuthManager::setProvider(TestUser::class);
        session_manager()->clear();
        AuthManager::resetUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 恢复原始提供者
        if ($this->originalProvider !== null) {
            AuthManager::setProvider($this->originalProvider);
        }
        session_manager()->clear();
        AuthManager::resetUser();
    }

    public function testHashMake(): void
    {
        $password = 'secret123';
        $hash = HashManager::make($password);

        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
    }

    public function testHashCheck(): void
    {
        $password = 'secret123';
        $hash = HashManager::make($password);

        $this->assertTrue(HashManager::check($password, $hash));
        $this->assertFalse(HashManager::check('wrong', $hash));
    }

    public function testHashBcrypt(): void
    {
        $password = 'secret123';
        $hash = HashManager::bcrypt($password);

        $this->assertNotEmpty($hash);
        $this->assertTrue(HashManager::check($password, $hash));
    }

    public function testHashNeedsRehash(): void
    {
        $password = 'secret123';
        $hash = HashManager::make($password);

        // 默认情况下不需要重新哈希
        $this->assertFalse(HashManager::needsRehash($hash));

        // 使用不同的 cost 值会需要重新哈希
        $this->assertTrue(HashManager::needsRehash($hash, ['cost' => 12]));
    }

    public function testHashGetInfo(): void
    {
        $password = 'secret123';
        $hash = HashManager::make($password);

        $info = HashManager::getInfo($hash);

        $this->assertTrue(is_array($info));
        $this->assertArrayHasKey('algo', $info);
        $this->assertArrayHasKey('algoName', $info);
    }

    public function testAuthCheckReturnsFalseWhenNoUser(): void
    {
        $this->assertFalse(AuthManager::check());
        $this->assertTrue(AuthManager::guest());
    }

    public function testAuthLogin(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        AuthManager::login($user);

        $this->assertTrue(AuthManager::check());
        $this->assertFalse(AuthManager::guest());
        $this->assertEquals(1, AuthManager::id());
    }

    public function testAuthLogout(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        AuthManager::login($user);

        $this->assertTrue(AuthManager::check());

        AuthManager::logout();

        $this->assertFalse(AuthManager::check());
        $this->assertNull(AuthManager::id());
    }

    public function testAuthLoginUsingId(): void
    {
        $user = AuthManager::loginUsingId(1);

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
        $this->assertTrue(AuthManager::check());
    }

    public function testAuthAttempt(): void
    {
        // 正确凭据
        $result = AuthManager::attempt([
            'email' => 'test@example.com',
            'password' => 'secret123'
        ]);

        $this->assertTrue($result);
        $this->assertTrue(AuthManager::check());

        // 登出再测试错误凭据
        AuthManager::logout();

        $result = AuthManager::attempt([
            'email' => 'test@example.com',
            'password' => 'wrong'
        ]);

        $this->assertFalse($result);
        $this->assertFalse(AuthManager::check());
    }

    public function testAuthValidate(): void
    {
        // 正确凭据
        $result = AuthManager::validate([
            'email' => 'test@example.com',
            'password' => 'secret123'
        ]);

        $this->assertTrue($result);

        // 错误凭据
        $result = AuthManager::validate([
            'email' => 'test@example.com',
            'password' => 'wrong'
        ]);

        $this->assertFalse($result);
    }

    public function testAuthUser(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        AuthManager::login($user);

        $authUser = AuthManager::user();

        $this->assertNotNull($authUser);
        $this->assertEquals(1, $authUser->id);
        $this->assertEquals('Test User', $authUser->name);
    }

    public function testRequestUserRestoresSessionBackedAuthAfterCacheReset(): void
    {
        $user = AuthManager::loginUsingId(1);

        $this->assertNotNull($user);
        AuthManager::resetUser();

        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/profile'], []);
        $request->setUserResolver(fn (): ?object => AuthManager::user());

        $resolved = $request->user();

        $this->assertNotNull($resolved);
        $this->assertEquals(1, $resolved->id);
    }

    public function testRequestUserIsNullAfterLogoutClearsSession(): void
    {
        AuthManager::loginUsingId(1);
        AuthManager::logout();

        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/profile'], []);
        $request->setUserResolver(fn (): ?object => AuthManager::user());

        $this->assertNull($request->user());
        $this->assertFalse(AuthManager::check());
    }

    public function testPasswordResetCreateToken(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        $token = PasswordResetManager::createToken($user);

        $this->assertNotEmpty($token);
        $this->assertTrue(is_string($token));
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testPasswordResetValidateToken(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        $token = PasswordResetManager::createToken($user);

        $validatedUser = PasswordResetManager::validateToken($token);

        $this->assertNotNull($validatedUser);
        $this->assertEquals(1, $validatedUser->id);
    }

    public function testPasswordResetInvalidToken(): void
    {
        $validatedUser = PasswordResetManager::validateToken('invalid_token');

        $this->assertNull($validatedUser);
    }

    public function testPasswordResetDeleteToken(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        $token = PasswordResetManager::createToken($user);

        PasswordResetManager::deleteToken($token);

        $validatedUser = PasswordResetManager::validateToken($token);

        $this->assertNull($validatedUser);
    }

    public function testPasswordReset(): void
    {
        $user = new TestUser(1, 'Test User', 'test@example.com');
        $user->password = HashManager::make('oldpassword');

        $token = PasswordResetManager::createToken($user);

        $result = PasswordResetManager::resetPassword($token, 'newpassword123');

        $this->assertTrue($result);
        $this->assertTrue(HashManager::check('newpassword123', $user->password));
    }

    public function testPasswordResetWithInvalidToken(): void
    {
        $result = PasswordResetManager::resetPassword('invalid_token', 'newpassword123');

        $this->assertFalse($result);
    }

    public function testPasswordResetTokenLifetime(): void
    {
        $originalLifetime = PasswordResetManager::getTokenLifetime();

        $this->assertEquals(3600, $originalLifetime);

        PasswordResetManager::setTokenLifetime(7200);

        $this->assertEquals(7200, PasswordResetManager::getTokenLifetime());

        // 恢复原始值
        PasswordResetManager::setTokenLifetime($originalLifetime);
    }

    public function testAuthSetProvider(): void
    {
        AuthManager::setProvider('Custom\\User');

        $this->assertEquals('Custom\\User', AuthManager::getProvider());
    }

    public function testAuthSetSessionKey(): void
    {
        AuthManager::setSessionKey('custom_auth_key');

        $this->assertEquals('custom_auth_key', AuthManager::getSessionKey());
    }
}

/**
 * 测试用户模型
 */
class TestUser
{
    public int $id;
    public string $name;
    public string $email;
    public string $password;

    private static array $users = [];

    public function __construct(int $id, string $name, string $email, string $password = 'secret123')
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = HashManager::make($password);

        // 存储到静态数组
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
        // 查找匹配的用户
        foreach (self::$users as $user) {
            if (isset($user->$column) && $user->$column === $value) {
                return $user;
            }
        }

        // 返回一个不存在的用户
        return new self(0, '', '');
    }

    public function first(): ?self
    {
        // 检查是否有匹配的用户
        foreach (self::$users as $user) {
            if (!empty($user->email)) {
                return $user;
            }
        }

        return null;
    }

    public static function reset(): void
    {
        // 创建默认测试用户
        self::$users = [];
        new self(1, 'Test User', 'test@example.com');
    }
}

// 初始化测试用户
TestUser::reset();
