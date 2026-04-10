<?php

declare(strict_types=1);

namespace App\Requests;

use Bin\Validation\FormRequest;

/**
 * StorePostRequest 表单请求
 */
class StorePostRequest extends FormRequest
{
    /**
     * 授权检查
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证规则
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [
            // 'name' => 'required|string|max:255',
        ];
    }

    /**
     * 自定义错误消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // 'name.required' => '名称不能为空',
        ];
    }
}
