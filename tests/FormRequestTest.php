<?php

declare(strict_types=1);

namespace Tests;

use Bin\Exception\AuthorizationException;
use Bin\Exception\ValidationException;
use Bin\Testing\TestCase;
use Bin\Validation\FormRequest;
use Bin\Validation\MessageBag;

/**
 * FormRequest 测试 — 验证、授权、生命周期钩子、错误响应、validated 数据
 */
class FormRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // ================================================================
    // 测试用 FormRequest 子类
    // ================================================================

    private function createTestFormRequest(array $rules, array $data = [], array $messages = [], bool $authorize = true): FormRequest
    {
        return new class ($rules, $data, $messages, $authorize) extends FormRequest {
            private array $testRules;
            private array $testMessages;
            private bool $testAuthorize;

            public function __construct(array $rules, array $data, array $messages, bool $authorize)
            {
                $this->testRules = $rules;
                $this->testMessages = $messages;
                $this->testAuthorize = $authorize;
                // 用传入数据构造请求
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                return $this->testRules;
            }

            public function messages(): array
            {
                return $this->testMessages;
            }

            public function authorize(): bool
            {
                return $this->testAuthorize;
            }

            // 暴露给测试
            public function testValidated(): array
            {
                return $this->validated();
            }

            public function testValidateResolved(): void
            {
                $this->validateResolved();
            }

            // 覆盖失败处理，不真正 exit
            public function failedValidation(\Bin\Validation\ValidationManager $validator): never
            {
                // 不 exit，抛异常让测试捕获
                $errors = $validator->getErrors();
                $errorArray = $errors instanceof MessageBag ? $errors->all() : (is_array($errors) ? $errors : []);
                throw new \RuntimeException('VALIDATION_FAILED: ' . json_encode($errorArray), 422);
            }

            // 覆盖授权失败处理，不使用 AuthorizationException
            protected function failedAuthorization(): never
            {
                throw new \RuntimeException('Unauthorized', 403);
            }
        };
    }

    /**
     * 创建使用真实异常的 FormRequest（集成测试用）
     */
    private function createRealFormRequest(array $rules, array $data = [], bool $authorize = true): FormRequest
    {
        return new class ($rules, $data, $authorize) extends FormRequest {
            private array $testRules;
            private bool $testAuthorize;

            /** @var bool prepareForValidation 是否被调用 */
            public bool $prepareCalled = false;

            /** @var bool passedValidation 是否被调用 */
            public bool $passedCalled = false;

            /** @var array<\Closure> 测试用 after 回调 */
            public array $testAfterCallbacks = [];

            public function __construct(array $rules, array $data, bool $authorize)
            {
                $this->testRules = $rules;
                $this->testAuthorize = $authorize;
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                return $this->testRules;
            }

            public function authorize(): bool
            {
                return $this->testAuthorize;
            }

            protected function prepareForValidation(): void
            {
                $this->prepareCalled = true;
            }

            protected function passedValidation(): void
            {
                $this->passedCalled = true;
            }

            public function testValidateResolved(): void
            {
                foreach ($this->testAfterCallbacks as $cb) {
                    $this->after($cb);
                }
                $this->validateResolved();
            }

            public function testValidated(): array
            {
                return $this->validated();
            }
        };
    }

    // ================================================================
    // 基本验证
    // ================================================================

    public function testValidDataPasses(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required|string'],
            ['name' => 'John']
        );

        $request->testValidateResolved();
        $validated = $request->testValidated();

        $this->assertEquals('John', $validated['name']);
    }

    public function testInvalidDataFails(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required|string'],
            []  // 缺少 name
        );

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertEquals(422, $e->getCode());
            $this->assertStringContainsString('VALIDATION_FAILED', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected validation failure');
    }

    public function testMultipleRulesValidation(): void
    {
        $request = $this->createTestFormRequest(
            [
                'name' => 'required|string',
                'email' => 'required|email',
                'age' => 'required|integer',
            ],
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'age' => '25',
            ]
        );

        $request->testValidateResolved();
        $validated = $request->testValidated();

        $this->assertEquals('Alice', $validated['name']);
        $this->assertEquals('alice@example.com', $validated['email']);
        $this->assertEquals('25', $validated['age']);
    }

    public function testValidatedOnlyReturnsDeclaredFields(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required|string'],
            ['name' => 'John', 'extra_field' => 'should_not_appear']
        );

        $request->testValidateResolved();
        $validated = $request->testValidated();

        $this->assertEquals('John', $validated['name']);
        $this->assertArrayNotHasKey('extra_field', $validated);
    }

    // ================================================================
    // 授权检查
    // ================================================================

    public function testAuthorizeRejects(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test'],
            [],
            false  // authorize = false
        );

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertEquals(403, $e->getCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected authorization failure');
    }

    public function testAuthorizePasses(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test'],
            [],
            true
        );

        $request->testValidateResolved();
        $this->assertEquals('test', $request->testValidated()['name']);
    }

    /**
     * 集成测试：授权失败应抛出 AuthorizationException
     */
    public function testAuthorizeRejectsThrowsAuthorizationException(): void
    {
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test'],
            false
        );

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (AuthorizationException $e) {
            $thrown = true;
        } catch (\Exception $e) {
            // fallback
        }
        $this->assertTrue($thrown, 'Expected AuthorizationException');
    }

    // ================================================================
    // 自定义消息
    // ================================================================

    public function testCustomMessages(): void
    {
        $request = $this->createTestFormRequest(
            ['email' => 'required|email'],
            ['email' => 'not-an-email'],
            ['email.email' => '请输入有效的邮箱地址']
        );

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('VALIDATION_FAILED', $e->getMessage());
        }
        $this->assertTrue($thrown);
    }

    // ================================================================
    // 重复验证不执行
    // ================================================================

    public function testValidateResolvedOnlyRunsOnce(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $request->testValidateResolved();
        $request->testValidateResolved(); // 第二次调用应该被跳过

        $validated = $request->testValidated();
        $this->assertEquals('test', $validated['name']);
    }

    // ================================================================
    // FormRequest 继承 Request
    // ================================================================

    public function testFormRequestHasInputAccess(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'John', 'email' => 'john@test.com']
        );

        // FormRequest 继承 Request 的所有方法
        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('john@test.com', $request->input('email'));
    }

    public function testFormRequestAllData(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'John', 'extra' => 'data']
        );

        $all = $request->all();
        $this->assertEquals('John', $all['name']);
        $this->assertEquals('data', $all['extra']);
    }

    // ================================================================
    // getValidator
    // ================================================================

    public function testGetValidatorBeforeValidation(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $this->assertNull($request->getValidator());

        $request->testValidateResolved();
        $this->assertNotNull($request->getValidator());
    }

    // ================================================================
    // 空规则
    // ================================================================

    public function testEmptyRulesPasses(): void
    {
        $request = $this->createTestFormRequest(
            [],
            ['anything' => 'goes']
        );

        $request->testValidateResolved();
        $validated = $request->testValidated();
        $this->assertEquals([], $validated);
    }

    // ================================================================
    // 多字段部分失败
    // ================================================================

    public function testPartialFailureAllFails(): void
    {
        $request = $this->createTestFormRequest(
            [
                'name' => 'required',
                'email' => 'required|email',
            ],
            ['name' => 'John'] // email 缺失
        );

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertEquals(422, $e->getCode());
        }
        $this->assertTrue($thrown);
    }

    // ================================================================
    // 生命周期钩子
    // ================================================================

    /**
     * prepareForValidation 在验证前被调用
     */
    public function testPrepareForValidationHookCalled(): void
    {
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $request->testValidateResolved();

        $this->assertTrue($request->prepareCalled, 'prepareForValidation should be called');
    }

    /**
     * passedValidation 在验证通过后被调用
     */
    public function testPassedValidationHookCalled(): void
    {
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $request->testValidateResolved();

        $this->assertTrue($request->passedCalled, 'passedValidation should be called');
    }

    /**
     * passedValidation 在验证失败时不被调用
     */
    public function testPassedValidationNotCalledOnFailure(): void
    {
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            []  // 缺少 name
        );

        try {
            $request->testValidateResolved();
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertFalse($request->passedCalled, 'passedValidation should NOT be called on failure');
    }

    /**
     * prepareForValidation 在验证前被调用，可用于修改输入
     */
    public function testPrepareForValidationCanModifyInput(): void
    {
        $request = new class (['name' => 'required|string'], ['name' => '']) extends FormRequest {
            private array $testRules;

            public function __construct(array $rules, array $data)
            {
                $this->testRules = $rules;
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                return $this->testRules;
            }

            protected function prepareForValidation(): void
            {
                // 补填默认值
                if (empty($this->input('name'))) {
                    $this->merge(['name' => 'default-name']);
                }
            }

            public function testValidateResolved(): void
            {
                $this->validateResolved();
            }

            public function testValidated(): array
            {
                return $this->validated();
            }
        };

        $request->testValidateResolved();
        $validated = $request->testValidated();

        $this->assertEquals('default-name', $validated['name']);
    }

    // ================================================================
    // after 回调
    // ================================================================

    /**
     * after 回调在验证后执行
     */
    public function testAfterCallbackExecuted(): void
    {
        $called = false;
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );
        $request->testAfterCallbacks[] = function (\Bin\Validation\ValidationManager $validator) use (&$called) {
            $called = true;
        };

        $request->testValidateResolved();

        $this->assertTrue($called, 'after callback should be executed');
    }

    /**
     * after 回调可以追加额外错误
     */
    public function testAfterCallbackCanAddErrors(): void
    {
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );
        $request->testAfterCallbacks[] = function (\Bin\Validation\ValidationManager $validator) {
            $validator->getErrors()->add('custom', 'Custom validation error');
        };

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (ValidationException $e) {
            $thrown = true;
            $errors = $e->getErrors();
            $flatErrors = is_array($errors) ? array_merge(...array_values(array_map(
                fn ($e) => is_array($e) ? $e : [$e],
                $errors
            ))) : [$errors];
            $this->assertStringContainsString('Custom validation error', implode(' ', $flatErrors));
        }
        $this->assertTrue($thrown, 'Should fail due to after callback error');
    }

    /**
     * 多个 after 回调按注册顺序执行
     */
    public function testMultipleAfterCallbacksRunInOrder(): void
    {
        $order = [];
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );
        $request->testAfterCallbacks[] = function (\Bin\Validation\ValidationManager $v) use (&$order) {
            $order[] = 'first';
        };
        $request->testAfterCallbacks[] = function (\Bin\Validation\ValidationManager $v) use (&$order) {
            $order[] = 'second';
        };

        $request->testValidateResolved();

        $this->assertEquals(['first', 'second'], $order);
    }

    /**
     * after 回调在验证失败时不执行
     */
    public function testAfterCallbacksNotCalledOnRuleFailure(): void
    {
        $called = false;
        $request = $this->createRealFormRequest(
            ['name' => 'required'],
            []  // name missing
        );
        $request->testAfterCallbacks[] = function (\Bin\Validation\ValidationManager $v) use (&$called) {
            $called = true;
        };

        try {
            $request->testValidateResolved();
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertFalse($called, 'after callback should NOT be called when validation fails');
    }

    // ================================================================
    // 错误袋
    // ================================================================

    public function testDefaultErrorBag(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $this->assertEquals('default', $request->errorBag());
    }

    public function testSetErrorBag(): void
    {
        $request = $this->createTestFormRequest(
            ['name' => 'required'],
            ['name' => 'test']
        );

        $result = $request->setErrorBag('custom');
        $this->assertEquals('custom', $request->errorBag());
        $this->assertSame($request, $result);
    }

    // ================================================================
    // attributes 别名
    // ================================================================

    public function testAttributesPassedToValidator(): void
    {
        $request = new class (['email' => 'required|email'], ['email' => 'not-an-email']) extends FormRequest {
            private array $testRules;

            public function __construct(array $rules, array $data)
            {
                $this->testRules = $rules;
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                return $this->testRules;
            }

            public function attributes(): array
            {
                return ['email' => '邮箱地址'];
            }

            public function testValidateResolved(): void
            {
                $this->validateResolved();
            }
        };

        $thrown = false;
        try {
            $request->testValidateResolved();
        } catch (ValidationException $e) {
            $thrown = true;
            $errors = $e->getErrors();
            // 错误消息应使用别名 "邮箱地址"
            $hasAlias = false;
            foreach ($errors as $error) {
                $msgs = is_array($error) ? $error : [$error];
                foreach ($msgs as $msg) {
                    if (str_contains((string) $msg, '邮箱地址')) {
                        $hasAlias = true;
                        break 2;
                    }
                }
            }
            $this->assertTrue($hasAlias, 'Error message should use attribute alias');
        }
        $this->assertTrue($thrown);
    }

    // ================================================================
    // 生命周期顺序验证
    // ================================================================

    /**
     * 验证完整的生命周期顺序
     */
    public function testLifecycleOrder(): void
    {
        $log = [];
        $request = new class (['name' => 'required'], ['name' => 'test'], $log) extends FormRequest {
            private array $testRules;
            private array $logRef;

            public function __construct(array $rules, array $data, array &$log)
            {
                $this->testRules = $rules;
                $this->logRef = &$log;
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                $this->logRef[] = 'rules';
                return $this->testRules;
            }

            public function authorize(): bool
            {
                $this->logRef[] = 'authorize';
                return true;
            }

            protected function prepareForValidation(): void
            {
                $this->logRef[] = 'prepareForValidation';
            }

            protected function passedValidation(): void
            {
                $this->logRef[] = 'passedValidation';
            }

            public function testValidateResolved(): void
            {
                $this->validateResolved();
            }
        };

        $request->testValidateResolved();

        $this->assertEquals(
            ['authorize', 'prepareForValidation', 'rules', 'passedValidation'],
            $log
        );
    }

    /**
     * 授权失败时后续钩子不执行
     */
    public function testAuthorizationStopsLifecycle(): void
    {
        $log = [];
        $request = new class (['name' => 'required'], ['name' => 'test'], $log) extends FormRequest {
            private array $testRules;
            private array $logRef;

            public function __construct(array $rules, array $data, array &$log)
            {
                $this->testRules = $rules;
                $this->logRef = &$log;
                parent::__construct($data, [], ['REQUEST_METHOD' => 'POST'], []);
            }

            public function rules(): array
            {
                $this->logRef[] = 'rules';
                return $this->testRules;
            }

            public function authorize(): bool
            {
                $this->logRef[] = 'authorize';
                return false;  // 拒绝
            }

            protected function prepareForValidation(): void
            {
                $this->logRef[] = 'prepareForValidation';
            }

            public function testValidateResolved(): void
            {
                $this->validateResolved();
            }
        };

        try {
            $request->testValidateResolved();
        } catch (\Exception $e) {
            // expected
        }

        $this->assertEquals(['authorize'], $log);
    }
}
