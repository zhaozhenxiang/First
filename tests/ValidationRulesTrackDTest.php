<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\ConnectionManager;
use Bin\Testing\TestCase;
use Bin\Validation\Rule;
use Bin\Validation\ValidationManager;
use PDO;

/**
 * Track D 验证规则测试 — 未知规则失败、exclude、五条新规则
 */
class ValidationRulesTrackDTest extends TestCase
{
    protected ?PDO $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec('
            CREATE TABLE track_d_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                status TEXT DEFAULT NULL
            )
        ');
        $this->connection->exec("INSERT INTO track_d_users (email, status) VALUES ('a@example.com', 'active'), ('b@example.com', 'inactive')");

        ConnectionManager::setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        ConnectionManager::reset();
        parent::tearDown();
    }

    // ================================================================
    // D-0 未知规则不再静默放行
    // ================================================================

    public function testUnknownRuleThrowsInsteadOfPassing(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            ValidationManager::make(['name' => 'x'], ['name' => 'strring'])->validate();
        });
    }

    public function testUnknownRuleMessageNamesTheRule(): void
    {
        try {
            ValidationManager::make(['name' => 'x'], ['name' => 'requried'])->validate();
            $this->fail('Expected InvalidArgumentException for unknown rule');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('requried', $exception->getMessage());
        }
    }

    public function testCustomExtendedRulesStillPassThrough(): void
    {
        ValidationManager::extend('trackd_upper', static fn (string $field, mixed $value): bool => strtoupper((string) $value) === $value);

        $validator = ValidationManager::make(['code' => 'abc'], ['code' => 'trackd_upper']);
        $validator->validate();

        $this->assertTrue($validator->hasError('code'));
    }

    // ================================================================
    // D-0 exclude
    // ================================================================

    public function testExcludeRemovesFieldFromValidatedData(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'John', 'internal_token' => 'secret'],
            ['name' => 'required|string', 'internal_token' => 'exclude']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
        $this->assertArrayNotHasKey('internal_token', $validated);
        $this->assertEquals('John', $validated['name']);
    }

    public function testExcludeViaRuleBuilder(): void
    {
        $validator = ValidationManager::make(
            ['keep' => 'yes', 'drop' => 'no'],
            ['keep' => 'required', 'drop' => Rule::exclude()]
        );

        $validated = $validator->validate();

        $this->assertArrayNotHasKey('drop', $validated);
        $this->assertEquals('yes', $validated['keep']);
    }

    public function testExcludeStillRunsSiblingRulesBeforeRemoval(): void
    {
        $validator = ValidationManager::make(
            ['token' => 'short', 'name' => 'ok'],
            ['token' => 'exclude|min:10', 'name' => 'required']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('token'));
    }

    // ================================================================
    // D-1 accepted
    // ================================================================

    public function testAcceptedPassesForYesOnOneTrue(): void
    {
        foreach (['yes', 'on', '1', 1, true, 'true'] as $value) {
            $validator = ValidationManager::make(['terms' => $value], ['terms' => 'accepted']);
            $validator->validate();
            $this->assertFalse($validator->hasErrors(), 'accepted should pass for ' . var_export($value, true));
        }
    }

    public function testAcceptedFailsForNoAndUnset(): void
    {
        $validator = ValidationManager::make(['terms' => 'no'], ['terms' => 'accepted']);
        $validator->validate();
        $this->assertTrue($validator->hasError('terms'));

        $validator = ValidationManager::make([], ['terms' => 'accepted']);
        $validator->validate();
        $this->assertTrue($validator->hasError('terms'));
    }

    // ================================================================
    // D-1 required_if
    // ================================================================

    public function testRequiredIfRequiresWhenOtherMatches(): void
    {
        $validator = ValidationManager::make(
            ['plan' => 'pro', 'company' => ''],
            ['company' => 'required_if:plan,pro']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('company'));
    }

    public function testRequiredIfSkipsWhenOtherDiffers(): void
    {
        $validator = ValidationManager::make(
            ['plan' => 'free', 'company' => ''],
            ['company' => 'required_if:plan,pro']
        );

        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testRequiredIfSupportsMultipleValues(): void
    {
        $validator = ValidationManager::make(
            ['plan' => 'team', 'company' => ''],
            ['company' => 'required_if:plan,pro,team']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('company'));
    }

    public function testRequiredIfComparesNumericStringsLoosely(): void
    {
        $validator = ValidationManager::make(
            ['type' => '2', 'reason' => ''],
            ['reason' => 'required_if:type,2']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('reason'));
    }

    // ================================================================
    // D-1 required_with
    // ================================================================

    public function testRequiredWithRequiresWhenListedFieldPresent(): void
    {
        $validator = ValidationManager::make(
            ['first_name' => 'John', 'last_name' => ''],
            ['last_name' => 'required_with:first_name']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('last_name'));
    }

    public function testRequiredWithSkipsWhenListedFieldsEmpty(): void
    {
        $validator = ValidationManager::make(
            ['first_name' => '', 'last_name' => ''],
            ['last_name' => 'required_with:first_name']
        );

        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testRequiredWithMultipleFieldsAnyPresent(): void
    {
        $validator = ValidationManager::make(
            ['a' => '', 'b' => 'set', 'c' => ''],
            ['c' => 'required_with:a,b']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('c'));
    }

    // ================================================================
    // D-1 unique / exists
    // ================================================================

    public function testUniqueFailsOnDuplicateEmail(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'a@example.com'],
            ['email' => 'unique:track_d_users,email']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }

    public function testUniquePassesOnNewEmail(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'new@example.com'],
            ['email' => 'unique:track_d_users,email']
        );

        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testUniqueIgnoresExceptId(): void
    {
        // a@example.com 属于 id=1，更新场景忽略自身
        $validator = ValidationManager::make(
            ['email' => 'a@example.com'],
            ['email' => 'unique:track_d_users,email,1']
        );

        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testUniqueStillFailsWhenExceptMatchesOtherRow(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'a@example.com'],
            ['email' => 'unique:track_d_users,email,2']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }

    public function testUniqueDefaultsColumnToFieldName(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'b@example.com'],
            ['email' => 'unique:track_d_users']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }

    public function testUniqueSkipsEmptyValue(): void
    {
        $validator = ValidationManager::make(
            ['email' => ''],
            ['email' => 'unique:track_d_users,email']
        );

        $validator->validate();

        $this->assertFalse($validator->hasError('email'));
    }

    public function testExistsPassesForPresentValue(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'a@example.com'],
            ['email' => 'exists:track_d_users,email']
        );

        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testExistsFailsForMissingValue(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'missing@example.com'],
            ['email' => 'exists:track_d_users,email']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }

    public function testExistsSkipsEmptyValue(): void
    {
        $validator = ValidationManager::make(
            ['email' => ''],
            ['email' => 'exists:track_d_users,email']
        );

        $validator->validate();

        $this->assertFalse($validator->hasError('email'));
    }

    // ================================================================
    // Rule 构建器
    // ================================================================

    public function testRuleBuilderCompilesNewRules(): void
    {
        $this->assertEquals(
            ['accepted'],
            Rule::accepted()->compile()
        );
        $this->assertEquals(
            ['required_if:plan,pro'],
            Rule::requiredIf('plan', 'pro')->compile()
        );
        $this->assertEquals(
            ['required_with:a,b'],
            Rule::requiredWith('a', 'b')->compile()
        );
        $this->assertEquals(
            ['unique:users,email'],
            Rule::unique('users', 'email')->compile()
        );
        $this->assertEquals(
            ['unique:users'],
            Rule::unique('users')->compile()
        );
        $this->assertEquals(
            ['unique:users,email,1'],
            Rule::unique('users', 'email', 1)->compile()
        );
        $this->assertEquals(
            ['exists:users,email'],
            Rule::exists('users', 'email')->compile()
        );
        $this->assertEquals(
            ['exists:users'],
            Rule::exists('users')->compile()
        );
    }

    public function testRuleBuilderUniqueValidatesAgainstDatabase(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'a@example.com'],
            ['email' => Rule::unique('track_d_users', 'email')]
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }
}
