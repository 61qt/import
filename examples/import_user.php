<?php

use QT\Import\Task;
use Illuminate\Events\Dispatcher;
use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;

include "bootstrap.php";

class UserImport extends Task
{
    protected $fields = [
        'username'  => '账户名称',
        'user_type' => '用户类型',
        'password'  => '登陆密码',
        'name'      => '姓名',
        'phone'     => '手机号',
        'email'     => '邮箱地址',
    ];

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

    protected $messages = [
        'username.required' => '账户名称必填',
    ];

    protected function insertDB()
    {
        var_dump($this->rows);
    }
}

$path = __DIR__ . '/user.xlsx';
// 初始化导入任务
$task = new UserImport;
// 设置校验器（如果Ioc容器内有,会自动从容器中获取）
$task->setValidationFactory(new Factory(new Translator(new ArrayLoader, 'cn')));
// 设置事件触发器
$task->setEventDispatcher(new Dispatcher());
// 设置字典
$task->setDictionary('user_type', new Dict([
    '超级用户' => 1,
    '特殊用户' => 2,
    '普通用户' => 3,
]));

$task->complete(function (int $success) {
    echo "导入成功{$success}条";
});

$task->warning(function (array $errors) {
    var_dump($errors);
});

$task->failed(function (Throwable $e) {
    var_dump($e);
});

$task->progress(function (int $count) {
    var_dump($count);
});

// 生成导入模板
$template = $task->getImportTemplate();
$template->generateColumns();
$template->setOptionalColumns($task->getDictionaries());
$template->fillSimpleData([[
    'username'  => 'rayson',
    'user_type' => '超级用户',
    'password'  => '123456',
    'name'      => 'rayson',
    'phone'     => '13012345678',
    'email'     => 'example@example.com',
]]);

$template->save($path);

$task->handle($path);
