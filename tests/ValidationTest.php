<?php

declare(strict_types=1);

namespace Tests;

use Bin\Exception\ValidationException;
use Bin\Validation\ValidationManager;
use Bin\Testing\TestCase;

/**
 * 验证系统测试
 */
class ValidationTest extends TestCase
{
    public function testRequiredValidation(): void
    {
        $validator = ValidationManager::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $validated = $validator->validate();

        $this->assertTrue($validator->hasErrors());
        $this->assertTrue($validator->hasError('name'));
    }

    public function testRequiredValidationPasses(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
        $this->assertEquals('John', $validated['name']);
    }

    public function testStringValidation(): void
    {
        $validator = ValidationManager::make(
            ['name' => 123],
            ['name' => 'string']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testIntegerValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 'not_a_number'],
            ['age' => 'integer']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testNumericValidation(): void
    {
        $validator = ValidationManager::make(
            ['price' => 'abc'],
            ['price' => 'numeric']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testEmailValidation(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'not_an_email'],
            ['email' => 'email']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testEmailValidationPasses(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
        $this->assertEquals('test@example.com', $validated['email']);
    }

    public function testMinValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 15],
            ['age' => 'integer|min:18']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testMaxValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 150],
            ['age' => 'integer|max:120']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testMinStringLength(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'ab'],
            ['name' => 'string|min:3']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testMaxStringLength(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'This is a very long name'],
            ['name' => 'string|max:10']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testBetweenValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 15],
            ['age' => 'integer|between:18,65']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testInValidation(): void
    {
        $validator = ValidationManager::make(
            ['role' => 'admin'],
            ['role' => 'in:user,moderator']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testNotInValidation(): void
    {
        $validator = ValidationManager::make(
            ['username' => 'admin'],
            ['username' => 'not_in:admin,root']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testConfirmedValidation(): void
    {
        $validator = ValidationManager::make(
            ['password' => 'secret', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testConfirmedValidationPasses(): void
    {
        $validator = ValidationManager::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testSameValidation(): void
    {
        $validator = ValidationManager::make(
            ['password' => 'secret', 'confirm' => 'different'],
            ['password' => 'same:confirm']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testDifferentValidation(): void
    {
        $validator = ValidationManager::make(
            ['new_password' => 'secret', 'current_password' => 'secret'],
            ['new_password' => 'different:current_password']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testNullableValidation(): void
    {
        $validator = ValidationManager::make(
            ['bio' => null],
            ['bio' => 'nullable|string|max:500']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testArrayValidation(): void
    {
        $validator = ValidationManager::make(
            ['tags' => 'not_an_array'],
            ['tags' => 'array']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testBooleanValidation(): void
    {
        $validator = ValidationManager::make(
            ['active' => 'yes'],
            ['active' => 'boolean']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testBooleanValidationPasses(): void
    {
        $validator = ValidationManager::make(
            ['active' => true],
            ['active' => 'boolean']
        );

        $validated = $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function testUrlValidation(): void
    {
        $validator = ValidationManager::make(
            ['website' => 'not_a_url'],
            ['website' => 'url']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testIpValidation(): void
    {
        $validator = ValidationManager::make(
            ['ip_address' => 'not_an_ip'],
            ['ip_address' => 'ip']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testJsonValidation(): void
    {
        $validator = ValidationManager::make(
            ['data' => '{invalid json}'],
            ['data' => 'json']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testAlphaValidation(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'John123'],
            ['name' => 'alpha']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testAlphaNumValidation(): void
    {
        $validator = ValidationManager::make(
            ['username' => 'user-name'],
            ['username' => 'alpha_num']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testAlphaDashValidation(): void
    {
        $validator = ValidationManager::make(
            ['username' => 'user name'],
            ['username' => 'alpha_dash']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testMultipleRules(): void
    {
        $validator = ValidationManager::make(
            ['email' => 'invalid'],
            ['email' => 'required|email|max:100']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
        $this->assertTrue($validator->hasError('email'));
    }

    public function testCustomErrorMessage(): void
    {
        $validator = ValidationManager::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $validator->setCustomMessages([
            'name.required' => '请输入姓名'
        ]);

        $validator->validate();

        $this->assertEquals('请输入姓名', $validator->getError('name')[0]);
    }

    public function testFieldAlias(): void
    {
        $validator = ValidationManager::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $validator->setAliases(['name' => '姓名']);

        $validator->validate();

        $this->assertStringContainsString('姓名', $validator->getError('name')[0]);
    }

    public function testDotNotation(): void
    {
        $validator = ValidationManager::make(
            ['user' => ['email' => 'invalid']],
            ['user.email' => 'required|email']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testGetValidatedData(): void
    {
        $validator = ValidationManager::make(
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'required|string', 'email' => 'required|email']
        );

        $validated = $validator->validate();

        $this->assertEquals('John', $validated['name']);
        $this->assertEquals('john@example.com', $validated['email']);
    }

    public function testQuickValidate(): void
    {
        $validated = ValidationManager::quickValidate(
            ['name' => 'John'],
            ['name' => 'required|string']
        );

        $this->assertEquals('John', $validated['name']);
    }

    public function testCheckValidation(): void
    {
        $result = ValidationManager::check(
            ['name' => 'John'],
            ['name' => 'required|string']
        );

        $this->assertTrue($result);
    }

    public function testCheckValidationFails(): void
    {
        $result = ValidationManager::check(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertFalse($result);
    }

    public function testValidationException(): void
    {
        $exceptionThrown = false;

        try {
            $validator = ValidationManager::make(
                ['name' => ''],
                ['name' => 'required']
            );

            $validator->throwOnFail()->validate();
        } catch (ValidationException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown);
    }

    public function testGetFirstError(): void
    {
        $validator = ValidationManager::make(
            ['name' => '', 'email' => 'invalid'],
            ['name' => 'required', 'email' => 'email']
        );

        $validator->validate();

        $firstError = $validator->getFirstError();

        $this->assertNotEmpty($firstError);
    }

    public function testGtValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 18, 'min_age' => 21],
            ['age' => 'gt:min_age']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testLtValidation(): void
    {
        $validator = ValidationManager::make(
            ['age' => 25, 'max_age' => 21],
            ['age' => 'lt:max_age']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testStartsWithValidation(): void
    {
        $validator = ValidationManager::make(
            ['phone' => '123456789'],
            ['phone' => 'starts_with:+,00']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function testEndsWithValidation(): void
    {
        $validator = ValidationManager::make(
            ['filename' => 'document.pdfx'],
            ['filename' => 'ends_with:.pdf,.doc,.txt']
        );

        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }
}
