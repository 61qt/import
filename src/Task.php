<?php

namespace QT\Import;

use Throwable;
use RuntimeException;
use QT\Import\Traits\Events;
use QT\Import\Traits\WithTemplate;
use Illuminate\Database\Connection;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Exceptions\ImportError;
use Illuminate\Database\Eloquent\Model;
use QT\Import\Traits\CheckTableDuplicated;
use QT\Import\Exceptions\ValidationException;

/**
 * Import Task
 *
 * @package QT\Import
 */
abstract class Task
{
    use Events;
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
    protected $memoryLimit = '512M';

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
     * @var array<ImportError>
     */
    protected $errors = [];

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
     * 获取导入字段
     *
     * @return array
     */
    abstract public function getFields(): array;

    /**
     * 开始处理异步导入任务
     *
     * @param iterable $rows
     * @param array $options
     */
    public function handle(iterable $rows, array $options = [])
    {
        ini_set('memory_limit', $this->memoryLimit);

        $this->options = $options;

        if (!empty($this->model) && is_subclass_of($this->model, Model::class)) {
            /** @var Connection $connection */
            $connection = $this->model::query()->getConnection();
        }

        try {
            // 任务开始前对option内容进行检查处理
            $this->beforeImport($options);
            // 初始化错误信息
            $this->bootDictErrorMessages();
            $this->bootCheckTableDuplicated();
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

            try {
                $data = $this->checkAndFormatRow($row, $line);

                if (empty($data)) {
                    continue;
                }

                $this->rows[$line] = $row;
                // 冗余原始行数据
                $this->originalRows[$line] = $row;
            } catch (Throwable $e) {
                if (!$this->catch) {
                    throw $e;
                }

                // 整合错误信息文档
                $this->errors[$line] = new ImportError($row, $line, $e);
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
     * @return mixed
     * @throws RuntimeException
     */
    protected function checkAndFormatRow(array $data, int $line)
    {
        $errors = [];
        foreach ($this->dictionaries as $field => $dict) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = $this->formatDict($data[$field], $dict);

            if ($value !== false) {
                $data[$field] = $value;
            } else {
                $errors[$field] = $this->dictErrorMessages[$field];
            }
        }

        $this->throwNotEmpty($errors, $line);

        [$result, $line] = $this->formatRow(
            ...$this->checkRow($data, $line)
        );

        // 进行唯一性检查,保证唯一索引字段在excel内不会有重复
        $this->checkTableDuplicated($result, $line);

        return $result;
    }

    /**
     * 批量处理行信息
     */
    protected function checkAndFormatRows()
    {
        // 批量进行唯一索引检查,防止唯一索引冲突
        foreach ($this->validateRows($this->rows) as $line => $errMsg) {
            $row = $this->originalRows[$line];

            $this->errors[$line] = new ImportError(
                $row, $line, new ValidationException(join('; ', $errMsg)
            ));

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
