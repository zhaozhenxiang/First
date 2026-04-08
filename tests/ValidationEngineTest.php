<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Validation\MessageBag;
use Bin\Validation\ValidationManager;
use Bin\Validation\ValidationRule;
use Bin\Validation\Rule;

/**
 * 验证引擎增强测试 — MessageBag、自定义规则、Rule 构建器、条件验证、新规则
 */
class ValidationEngineTest extends TestCase
{
    // ================================================================
    // MessageBag
    // ================================================================

    public function testMessageBagAddAndGet(): void
    {
        $bag = new MessageBag();
        $bag->add('name', 'Name is required');

        $this->assertEquals(['Name is required'], $bag->get('name'));
    }

    public function testMessageBagMultipleErrorsPerField(): void
    {
        $bag = new MessageBag();
        $bag->add('email', 'Email is required');
        $bag->add('email', 'Email must be valid');

        $this->assertCount(2, $bag->get('email'));
    }

    public function testMessageBagFirst(): void
    {
        $bag = new MessageBag();
        $bag->add('name', 'First error');
        $bag->add('name', 'Second error');

        $this->assertEquals('First error', $bag->first('name'));
    }

    public function testMessageBagFirstReturnsEmptyForMissingKey(): void
    {
        $bag = new MessageBag();
        $this->assertEquals('', $bag->first('nonexistent'));
    }

    public function testMessageBagHas(): void
    {
        $bag = new MessageBag();
        $this->assertFalse($bag->has());

        $bag->add('name', 'error');
        $this->assertTrue($bag->has());
        $this->assertTrue($bag->has('name'));
        $this->assertFalse($bag->has('email'));
    }

    public function testMessageBagWildcardHas(): void
    {
        $bag = new MessageBag();
        $bag->add('users.0.name', 'error');
        $bag->add('users.1.name', 'error');

        $this->assertTrue($bag->has('users.*.name'));
        $this->assertFalse($bag->has('posts.*.name'));
    }

    public function testMessageBagIsEmpty(): void
    {
        $bag = new MessageBag();
        $this->assertTrue($bag->isEmpty());
        $this->assertFalse($bag->isNotEmpty());

        $bag->add('field', 'error');
        $this->assertFalse($bag->isEmpty());
        $this->assertTrue($bag->isNotEmpty());
    }

    public function testMessageBagAll(): void
    {
        $bag = new MessageBag(['name' => 'error1', 'email' => ['e1', 'e2']]);

        $all = $bag->all();
        $this->assertEquals(['error1'], $all['name']);
        $this->assertEquals(['e1', 'e2'], $all['email']);
    }

    public function testMessageBagUniqueMessages(): void
    {
        $bag = new MessageBag();
        $bag->add('a', 'same error');
        $bag->add('b', 'same error');
        $bag->add('c', 'different error');

        $unique = $bag->uniqueMessages();
        $this->assertCount(2, $unique);
    }

    public function testMessageBagKeys(): void
    {
        $bag = new MessageBag();
        $bag->add('name', 'e');
        $bag->add('email', 'e');

        $this->assertEquals(['name', 'email'], $bag->keys());
    }

    public function testMessageBagCount(): void
    {
        $bag = new MessageBag();
        $bag->add('a', 'e1');
        $bag->add('a', 'e2');
        $bag->add('b', 'e3');

        $this->assertEquals(3, $bag->count());
    }

    public function testMessageBagMerge(): void
    {
        $bag1 = new MessageBag();
        $bag1->add('a', 'error1');

        $bag2 = new MessageBag();
        $bag2->add('b', 'error2');

        $bag1->merge($bag2);

        $this->assertTrue($bag1->has('a'));
        $this->assertTrue($bag1->has('b'));
    }

    public function testMessageBagClear(): void
    {
        $bag = new MessageBag();
        $bag->add('a', 'error');
        $bag->clear();

        $this->assertTrue($bag->isEmpty());
    }

    public function testMessageBagToArray(): void
    {
        $bag = new MessageBag(['name' => 'error']);
        $this->assertEquals(['name' => ['error']], $bag->toArray());
    }

    public function testMessageBagConstructorWithStringValues(): void
    {
        $bag = new MessageBag(['a' => 'single', 'b' => ['m1', 'm2']]);

        $this->assertEquals(['single'], $bag->get('a'));
        $this->assertEquals(['m1', 'm2'], $bag->get('b'));
    }

    // ================================================================
    // ValidationRule 自定义规则对象
    // ================================================================

    public function testCustomValidationRuleObject(): void
    {
        $uppercaseRule = new class extends ValidationRule {
            public function passes(string $attribute, mixed $value): bool
            {
                return is_string($value) && strtoupper($value) === $value;
            }
            public function message(): string
            {
                return ':attribute 必须全部大写';
            }
        };

        $v = ValidationManager::make(
            ['code' => 'ABC'],
            ['code' => [$uppercaseRule]]
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testCustomValidationRuleObjectFails(): void
    {
        $uppercaseRule = new class extends ValidationRule {
            public function passes(string $attribute, mixed $value): bool
            {
                return is_string($value) && strtoupper($value) === $value;
            }
            public function message(): string
            {
                return ':attribute 必须全部大写';
            }
        };

        $v = ValidationManager::make(
            ['code' => 'abc'],
            ['code' => [$uppercaseRule]]
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
        $this->assertStringContainsString('必须全部大写', $v->getErrors()->first('code'));
    }

    public function testMixedStringAndObjectRules(): void
    {
        $customRule = new class extends ValidationRule {
            public function passes(string $attribute, mixed $value): bool
            {
                return is_string($value) && str_starts_with($value, 'PREFIX_');
            }
            public function message(): string
            {
                return ':attribute 必须以 PREFIX_ 开头';
            }
        };

        // 字符串规则 + 对象规则混合
        $v = ValidationManager::make(
            ['code' => 'PREFIX_123'],
            ['code' => ['string', $customRule]]
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testClosureRule(): void
    {
        $v = ValidationManager::make(
            ['age' => 15],
            ['age' => [fn($attr, $val) => $val >= 18 ? true : ':attribute 必须 18 岁以上']]
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testClosureRuleReturnsFalse(): void
    {
        $v = ValidationManager::make(
            ['age' => 15],
            ['age' => [fn($attr, $val) => $val >= 18]]
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testClosureRulePasses(): void
    {
        $v = ValidationManager::make(
            ['age' => 25],
            ['age' => [fn($attr, $val) => $val >= 18]]
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    // ================================================================
    // Rule 流式构建器
    // ================================================================

    public function testRuleBuilderBasic(): void
    {
        $rules = Rule::required()->string()->max(255)->compile();

        $this->assertEquals(['required', 'string', 'max:255'], $rules);
    }

    public function testRuleBuilderEmail(): void
    {
        $rules = Rule::required()->email()->compile();

        $this->assertEquals(['required', 'email'], $rules);
    }

    public function testRuleBuilderNumeric(): void
    {
        $rules = Rule::required()->numeric()->between(0, 100)->compile();

        $this->assertEquals(['required', 'numeric', 'between:0,100'], $rules);
    }

    public function testRuleBuilderPassword(): void
    {
        $rules = Rule::required()->string()->min(8)->confirmed()->compile();

        $this->assertEquals(['required', 'string', 'min:8', 'confirmed'], $rules);
    }

    public function testRuleBuilderNullable(): void
    {
        $rules = Rule::nullable()->integer()->compile();

        $this->assertEquals(['nullable', 'integer'], $rules);
    }

    public function testRuleBuilderIn(): void
    {
        $rules = Rule::required()->in(['admin', 'user', 'guest'])->compile();

        $this->assertEquals(['required', 'in:admin,user,guest'], $rules);
    }

    public function testRuleBuilderRegex(): void
    {
        $rules = Rule::required()->regex('/^[A-Z]+$/')->compile();

        $this->assertEquals(['required', 'regex:/^[A-Z]+$/'], $rules);
    }

    public function testRuleBuilderToString(): void
    {
        $rule = Rule::required()->string()->max(255);

        $this->assertEquals('required|string|max:255', (string) $rule);
    }

    public function testRuleBuilderDirectInValidation(): void
    {
        $rule = Rule::required()->email();

        $v = ValidationManager::make(
            ['email' => 'test@example.com'],
            ['email' => $rule]
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testRuleBuilderAdvancedMethods(): void
    {
        $rules = Rule::required()->uuid()->compile();
        $this->assertEquals(['required', 'uuid'], $rules);

        $rules = Rule::required()->alphaDash()->compile();
        $this->assertEquals(['required', 'alpha_dash'], $rules);

        $rules = Rule::required()->startsWith('http', 'https')->compile();
        $this->assertEquals(['required', 'starts_with:http,https'], $rules);
    }

    // ================================================================
    // sometimes() 条件验证
    // ================================================================

    public function testSometimesAppliesWhenConditionTrue(): void
    {
        $v = ValidationManager::make(
            ['change_password' => true, 'password' => ''],
            []
        );
        $v->sometimes('password', 'required|string|min:8', fn($input) => !empty($input['change_password']));
        $v->validate();

        $this->assertTrue($v->hasErrors());
        $this->assertTrue($v->hasError('password'));
    }

    public function testSometimesSkipsWhenConditionFalse(): void
    {
        $v = ValidationManager::make(
            ['change_password' => false, 'password' => ''],
            ['password' => 'nullable']
        );
        $v->sometimes('password', 'required|string|min:8', fn($input) => !empty($input['change_password']));
        $v->validate();

        $this->assertFalse($v->hasErrors());
    }

    public function testSometimesWithValidData(): void
    {
        $v = ValidationManager::make(
            ['change_password' => true, 'password' => 'secure123'],
            ['password' => 'nullable']
        );
        $v->sometimes('password', 'required|string|min:8', fn($input) => !empty($input['change_password']));
        $v->validate();

        $this->assertFalse($v->hasErrors());
    }

    // ================================================================
    // 新增规则
    // ================================================================

    public function testUuidValidation(): void
    {
        $v = ValidationManager::make(
            ['id' => '550e8400-e29b-41d4-a716-446655440000'],
            ['id' => 'uuid']
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testUuidValidationFails(): void
    {
        $v = ValidationManager::make(
            ['id' => 'not-a-uuid'],
            ['id' => 'uuid']
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testMacAddressValidation(): void
    {
        $v = ValidationManager::make(
            ['mac' => '00:1A:2B:3C:4D:5E'],
            ['mac' => 'mac_address']
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testMacAddressValidationFails(): void
    {
        $v = ValidationManager::make(
            ['mac' => 'not-a-mac'],
            ['mac' => 'mac_address']
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testTimezoneValidation(): void
    {
        $v = ValidationManager::make(
            ['tz' => 'Asia/Shanghai'],
            ['tz' => 'timezone']
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testTimezoneValidationFails(): void
    {
        $v = ValidationManager::make(
            ['tz' => 'Invalid/Timezone'],
            ['tz' => 'timezone']
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testProhibitedValidation(): void
    {
        $v = ValidationManager::make(
            ['field' => ''],
            ['field' => 'prohibited']
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testProhibitedValidationFails(): void
    {
        $v = ValidationManager::make(
            ['field' => 'value'],
            ['field' => 'prohibited']
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    public function testDistinctValidation(): void
    {
        $v = ValidationManager::make(
            ['tags' => ['a', 'b', 'c']],
            ['tags' => 'array|distinct']
        );
        $v->validate();
        $this->assertFalse($v->hasErrors());
    }

    public function testDistinctValidationFails(): void
    {
        $v = ValidationManager::make(
            ['tags' => ['a', 'b', 'a']],
            ['tags' => 'array|distinct']
        );
        $v->validate();
        $this->assertTrue($v->hasErrors());
    }

    // ================================================================
    // MessageBag 集成到 ValidationManager
    // ================================================================

    public function testGetErrorsReturnsMessageBag(): void
    {
        $v = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $v->validate();

        $errors = $v->getErrors();
        $this->assertInstanceOf(MessageBag::class, $errors);
    }

    public function testGetErrorReturnsArray(): void
    {
        $v = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $v->validate();

        $error = $v->getError('name');
        $this->assertTrue(is_array($error));
        $this->assertCount(1, $error);
    }

    public function testGetErrorReturnsEmptyArrayForValidField(): void
    {
        $v = ValidationManager::make(['name' => 'John'], ['name' => 'required']);
        $v->validate();

        $this->assertEquals([], $v->getError('name'));
    }

    public function testHasErrorWithValidField(): void
    {
        $v = ValidationManager::make(['name' => 'John'], ['name' => 'required']);
        $v->validate();

        $this->assertFalse($v->hasError('name'));
    }

    // ================================================================
    // Config 结构验证
    // ================================================================

    public function testValidationConfigFileExists(): void
    {
        $this->assertTrue(file_exists(BASE_PATH . '/config/validation.php'));
    }

    public function testValidationConfigStructure(): void
    {
        $config = require BASE_PATH . '/config/validation.php';

        $this->assertArrayHasKey('locale', $config);
        $this->assertArrayHasKey('messages', $config);
        $this->assertArrayHasKey('aliases', $config);
    }
}
