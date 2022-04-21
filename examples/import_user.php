<?php

use QT\Import\Task;
use QT\Import\Handler;
use QT\Import\Dictionary;
use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;

include "bootstrap.php";

class UserImport extends Task
{
    protected $rules = [
        'username'  => 'required|string|min:6|max:12',
        'user_type' => 'required',
        'password'  => 'required|string|min:6|max:20',
        'name'      => 'string|max:10|min:2',
        'phone'     => 'string',
        'email'     => 'string|email',
    ];

    protected $remarks = [
        'phone' => '手机号码不限制',
    ];

    protected $optional = [
        'user_type',
    ];

    protected $messages = [
        'username.required' => '账户名称必填',
    ];

    public function getFields(): array
    {
        return [
            'username'  => '账户名称',
            'user_type' => '用户类型',
            'password'  => '登陆密码',
            'name'      => '姓名',
            'phone'     => '手机号',
            'email'     => '邮箱地址',
        ];
    }

    protected function insertDB()
    {
        // do something
    }

    public function beforeImport(array $input)
    {
        // 检查输入数据是否正确
    }

    public function afterImport(array $successful, array $fail)
    {
        var_dump($successful, $fail);
    }

    public function onReport(int $count)
    {
        // 上报进度
    }

    public function onFailed(Throwable $exception)
    {
        // 处理错误
    }
}

$path = __DIR__ . '/user.xlsx';
// 初始化导入任务
$task = new UserImport();
// 设置校验器（如果Ioc容器内有,会自动从容器中获取）
$task->setValidationFactory(new Factory(new Translator(new ArrayLoader(), 'cn')));
// 设置字典
$task->setDictionary('user_type', new Dictionary([
    '超级用户' => 1,
    '特殊用户' => 2,
    '普通用户' => 3,
]));

// 生成导入模板
$template = $task->getImportTemplate();
$template->fillSimpleData([
    // 正确数据
    [
        'username'  => 'rayson',
        'user_type' => '超级用户',
        'password'  => '123456',
        'name'      => 'rayson',
        'phone'     => '13012345678',
        'email'     => 'example@example.com',
    ],
    // 错误数据
    [
        'username'  => 'rayson2',
        'user_type' => '错误类型',
        'password'  => '123456',
        'name'      => 'rayson',
        'phone'     => '13012345678',
        'email'     => 'example@example.com',
    ],
]);

$template->save($path);

$handler = new Handler();
$handler->import($task, $path);
