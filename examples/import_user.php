<?php

use QT\Import\Task;
use QT\Import\Dictionary;
use QT\Import\MatchColumns;
use QT\Import\Traits\InsertDB;
use Illuminate\Validation\Factory;
use QT\Import\Readers\VtifulReader;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use QT\Import\Contracts\WithBatchInserts;

include "bootstrap.php";

class UserImport extends Task implements WithBatchInserts
{
    use InsertDB;

    protected $fields = [
        'username'  => '账户名称',
        'user_type' => '用户类型',
        'password'  => '登陆密码',
        'name'      => '姓名',
        'phone'     => '手机号',
        'birthday'  => '生日',
        'email'     => '邮箱地址',
    ];

    protected $rules = [
        'username'  => 'required|string|min:6|max:12',
        'user_type' => 'required',
        'password'  => 'required|string|min:6|max:20',
        'name'      => 'string|max:10|min:2',
        'phone'     => 'string',
        'birthday'  => 'required|date_format:Y-m-d',
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

    public function init(array $input = []): Task
    {
        $this->setDictionary('user_type', new Dictionary([
            '超级用户' => 1,
            '特殊用户' => 2,
            '普通用户' => 3,
        ]));

        $this->setValidationFactory(new Factory(new Translator(new ArrayLoader(), 'cn')));

        return $this;
    }

    public function getFields(array $input = []): array
    {
        return $this->fields;
    }

    public function getFileReader(string $filename): Iterator
    {
        return new VtifulReader($filename);
    }

    public function getMatchColumnFn(): callable
    {
        return new MatchColumns($this->getFields(), [
            'mode' => MatchColumns::TOLERANT_MODE,
        ]);
    }

    public function afterImport(array $successful, array $fail)
    {
        foreach ($fail as $row) {
            var_dump($row->getPrevious()->__toString());
        }
    }

    public function onReport(int $count)
    {
        // 上报进度
    }

    public function onFailed(Throwable $exception)
    {
        // 处理错误
    }

    public function insertDB()
    {
        // var_dump($this->rows);
    }
}

$path = __DIR__ . '/user.xlsx';

// 生成导入模板
$template = UserImport::template();

// 设置示例
$template->setExample([
    [
        'username'  => '次数填写用户米',
        'user_type' => '选择用户类型',
        'password'  => '登陆密码',
        'name'      => '用户昵称',
        'phone'     => '手机号码',
        'birthday'  => '生日日期',
        'email'     => '邮箱',
    ],
]);

$template->fillSimpleData([
    // 正确数据
    [
        'username'  => 'rayson',
        'user_type' => '超级用户',
        'password'  => '123456',
        'name'      => 'rayson',
        'phone'     => '13012345678',
        'birthday'  => '2021-12-01',
        'email'     => 'example@example.com',
    ],
    // 错误数据
    [
        'username'  => 'rayson2',
        'user_type' => '错误类型',
        'password'  => '123456',
        'name'      => 'rayson',
        'phone'     => '13012345678',
        'birthday'  => '2021-12-01',
        'email'     => 'example@example.com',
    ],
]);

$template->save($path);

UserImport::read($path);