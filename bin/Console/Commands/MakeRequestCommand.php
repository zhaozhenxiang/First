<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * 创建 FormRequest 命令
 */
class MakeRequestCommand extends Command
{
    public string $signature = 'make:request {name}';
    public string $description = 'Create a new form request class';

    public function execute(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Request name is required.');
            return 1;
        }

        // 验证名称格式
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            $this->error('Invalid request name. Use only letters, numbers and underscores.');
            return 1;
        }

        $directory = BASE_PATH . '/app/Requests';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $name . '.php';

        if (file_exists($path)) {
            $this->error("Request already exists: {$path}");
            return 1;
        }

        $content = $this->getStub($name);

        file_put_contents($path, $content);

        $this->success("Request created successfully: {$path}");
        $this->newLine();
        $this->comment('Use in controller:');
        $this->line("  public function store(\\App\\Requests\\{$name} \$request)");

        return 0;
    }

    private function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Requests;

use Bin\Validation\FormRequest;

class {$name} extends FormRequest
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
            // 'email' => 'required|email',
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

PHP;
    }
}
