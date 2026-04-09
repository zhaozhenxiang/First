<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Validation\FormRequest;
use Bin\Validation\MessageBag;

/**
 * FormRequest 测试 — 验证、授权、错误响应、validated 数据
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
}
