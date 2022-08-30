<?php

namespace QT\Import;

use DateTime;
use Throwable;
use QT\Import\Traits\Events;
use QT\Import\Traits\CheckMaxRow;
use QT\Import\Traits\WithTemplate;
use QT\Import\Exceptions\RowError;
use Illuminate\Database\Connection;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use Illuminate\Database\Eloquent\Model;
use QT\Import\Traits\CheckTableDuplicated;
use QT\Import\Exceptions\ValidationException;
use Illuminate\Validation\ValidationRuleParser;

/**
 * Import Task
 *
 * @package QT\Import
 */
abstract class Task
{
    use Events;
    use CheckMaxRow;
    use WithTemplate;
    use RowsValidator;
    use CheckAndFormat;
    use CheckTableDuplicated;

    /**
     * excel单格字符长度不允许超过32767
     *
     * @var int
     */
    public const MAX_CHARACTERS_PER_CELL = 32767;

    /**
     * 导入的主体model
     *
     * @var string
     */
    protected $model;

    /**
     * 内存占用大小
     *
     * @var string
     */
    protected $memoryLimit = '128M';

    /**
     * 外部输入值
     *
     * @var array
     */
    protected $input = [];

    /**
     * 原始行数据
     *
     * @var array
     */
    protected $originalRows = [];

    /**
     * 待插入行
     *
     * @var array
     */
    protected $rows = [];

    /**
     * 错误行
     *
     * @var array<RowError>
     */
    protected $errors = [];

    /**
     * 需要格式化日期的字段
     * 如 'birthday' => 'Ymd'
     * @var array
     */
    protected $fieldDateFormats = [];

    /**
     * 是否捕获行错误
     * 可以在同步导入时直接抛出错误不执行后续逻辑
     * 异步任务执行时请开启该标识,防止异常中断任务
     *
     * @var bool
     */
    protected $catch = true;

    /**
     * 是否启用事务
     *
     * @var bool
     */
    protected $useTransaction = true;

    /**
     * 上次同步生成进度时间
     *
     * @var int
     */
    protected $reportAt;

    /**
     * 上报间隔时间
     *
     * @var int
     */
    protected $interval = 3;

    /**
     * 字段校验模式(默认使用宽松模式)
     * 
     * @var int
     */
    protected $fieldsCheckMode = Rows::TOLERANT_MODE;

    /**
     * 获取导入字段
     *
     * @param array $input
     * @return array
     */
    abstract public function getFields(array $input = []): array;

    /**
     * 获取字段校验模式
     * 
     * @param array $input
     * @return int
     */
    public function getFieldsCheckMode(array $input = []): int
    {
        return $this->fieldsCheckMode;
    }

    /**
     * 开始处理异步导入任务
     *
     * @param iterable $rows
     * @param array $input
     */
    public function handle(iterable $rows, array $input = [])
    {
        ini_set('memory_limit', $this->memoryLimit);

        $this->input = $input;

        if (!empty($this->model) && is_subclass_of($this->model, Model::class)) {
            /** @var Connection $connection */
            $connection = $this->model::query()->getConnection();
        }

        try {
            // 任务开始前对option内容进行检查处理
            $this->beforeImport($input);
            // 初始化错误信息
            $this->bootDictErrorMessages();
            $this->bootCheckTableDuplicated();
            $this->bootFieldDateFormats();
            // 处理行内容
            $this->processRows($rows);
            // 插入到db
            if (empty($connection) || !$this->useTransaction) {
                $this->insertDB();
            } else {
                $connection->transaction(fn () => $this->insertDB());
            }
            // 触发任务完成事件
            $this->afterImport($this->rows, $this->errors);
        } catch (Throwable $e) {
            $this->onFailed($e);
        }
    }

    /**
     * 加载需要格式化日期的字段规则
     *
     * @return void
     */
    protected function bootFieldDateFormats()
    {
        foreach ($this->rules as $field => $rules) {
            if (empty($rules)) {
                $rules = [];
            }

            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $rules = array_reduce($rules, function ($result, $rule) {
                [$rule, $params] = ValidationRuleParser::parse($rule);

                $result[$rule] = $params;

                return $result;
            }, []);

            if (isset($rules['DateFormat']) && !isset($this->fieldDateFormats[$field])) {
                $this->fieldDateFormats[$field] = $rules['DateFormat'][0];
            }
        }
    }

    /**
     * 处理上传文件
     *
     * @param iterable $rows
     */
    protected function processRows(iterable $rows)
    {
        foreach ($rows as $line => $row) {
            // 整行都是空的就忽略
            if (empty($row)) {
                continue;
            }

            $this->checkMaxRow($line);

            // 提前格式化datetime类型
            foreach ($this->fieldDateFormats as $field => $format) {
                if ($row[$field] instanceof DateTime) {
                    $row[$field] = $row[$field]->format($format);
                }
            }

            try {
                $data = $this->checkAndFormatRow($row, $line);

                if (empty($data)) {
                    continue;
                }

                $this->rows[$line] = $data;
                // 冗余原始行数据
                $this->originalRows[$line] = $row;
            } catch (Throwable $e) {
                if (!$this->catch) {
                    throw $e;
                }

                // 整合错误信息文档
                $this->errors[$line] = new RowError($row, $line, $e);
            }

            $now = time();
            if ($now > $this->reportAt + $this->interval) {
                $this->onReport($line);

                $this->reportAt = $now;
            }
        }

        // 从excel中提取的数据进行批量处理
        $this->checkAndFormatRows();
    }

    /**
     * 检查并格式化一行数据
     *
     * @param array $data
     * @param int $line
     * @return array
     * @throws ValidationException
     */
    protected function checkAndFormatRow(array $data, int $line): array
    {
        $errors = [];
        foreach ($this->optional as $field) {
            if (!isset($data[$field]) || empty($this->dictionaries[$field])) {
                continue;
            }

            $value = $this->formatDict($data[$field], $this->dictionaries[$field]);

            if ($value !== false) {
                $data[$field] = $value;
            } else {
                $errors[$field] = $this->dictErrorMessages[$field];
            }
        }

        $this->throwNotEmpty($errors, $line);

        [$result, $line] = $this->formatRow(...$this->checkRow($data, $line));

        // 进行唯一性检查,保证唯一索引字段在excel内不会有重复
        $this->checkTableDuplicated($result, $line);

        return $result;
    }

    /**
     * 批量处理行信息
     *
     * @return void
     */
    protected function checkAndFormatRows()
    {
        // 批量进行唯一索引检查,防止唯一索引冲突
        foreach ($this->validateRows($this->rows) as $line => $errMsg) {
            $row = $this->originalRows[$line];

            $this->errors[$line] = new RowError(
                $row,
                $line,
                new ValidationException(join('; ', $errMsg))
            );

            unset($this->rows[$line]);
        }

        // 数据校验完成后,清空冗余数据,释放内存
        $this->originalRows = [];
    }

    /**
     * 批量插入
     *
     * @return void
     */
    protected function insertDB()
    {
    }
}
