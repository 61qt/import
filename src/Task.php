<?php

namespace QT\Import;

use Throwable;
use RuntimeException;
use QT\Import\Traits\Events;
use QT\Import\Traits\ParseXlsx;
use QT\Import\Traits\RowsValidator;
use Illuminate\Database\Connection;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Exceptions\ImportError;
use Illuminate\Database\Eloquent\Model;
use QT\Import\Traits\CheckTableDuplicated;

/**
 * Import Task
 *
 * @package QT\Import
 */
abstract class Task
{
    use Events;
    use ParseXlsx;
    use RowsValidator;
    use CheckAndFormat;
    use CheckTableDuplicated;

    /**
     * excel单格字符长度不允许超过32767
     *
     * @var int
     */
    const MAX_CHARACTERS_PER_CELL = 32767;

    /**
     * 导入的主体model
     * 
     * @var string
     */
    protected $model;

    /**
     * 允许导入字段 (需要配置)
     *
     * @var array
     */
    protected $fields = [];

    /**
     * 字段备注信息
     *
     * @var array
     */
    protected $remarks = [];

    /**
     * 下拉可选列
     *
     * @var array
     */
    protected $optional = [];

    /**
     * 内存占用大小
     *
     * @var string
     */
    protected $memoryLimit = '512M';

    /**
     * 待插入行
     *
     * @var array
     */
    protected $rows = [];

    /**
     * 错误行
     *
     * @var array
     */
    protected $errors = [];

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
     * 是否捕获错误
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
     * 开始处理异步导入任务
     *
     * @param string $filename
     * @param array $options
     */
    public function handle(string $filename, array $options = [])
    {
        ini_set('memory_limit', $this->memoryLimit);

        $this->options = $options;

        if (!empty($this->model) && is_subclass_of($this->model, Model::class)) {
            /** @var Connection $connection */
            $connection = $this->model::query()->getConnection();
        }

        try {
            // 支持在任务开始前对option内容进行检查处理
            if (method_exists($this, 'beforeImport')) {
                $this->beforeImport($options);
            }
            // 初始化错误信息
            $this->bootDictErrorMessages();
            $this->bootCheckTableDuplicated();
            // 处理excel文件
            $this->processFile($filename);
            // 从excel中提取的数据进行批量处理
            $this->checkAndFormatRows();
            // 插入到db
            if (empty($connection) || !$this->useTransaction) {
                $this->insertDB();
            } else {
                $connection->transaction(fn() => $this->insertDB());
            }
            // 触发任务完成事件
            $this->fireEvent('complete', count($this->rows), $this->errors);
        } catch (Throwable $e) {
            // 防止没有监听事件时无法获取错误信息
            if (empty($this->getEventDispatcher())) {
                throw $e;
            }

            $this->fireEvent('failed', $e);
        }
    }

    /**
     * 处理上传文件
     *
     * @param string $filename
     */
    protected function processFile(string $filename)
    {
        $this->reportAt = time();

        foreach ($this->praseFile($filename) as [$row, $line]) {
            try {
                $row = $this->checkAndFormatRow($row, $line);

                if (empty($row)) {
                    continue;
                }

                $this->rows[$line] = $row;
            } catch (Throwable $e) {
                if (!$this->catch) {
                    throw $e;
                }

                // 整合错误信息文档
                $this->errors[$line] = new ImportError($this->originalRows[$line], $line, $e);
                // 错误行不保留原始数据
                unset($this->originalRows[$line]);
            }

            // 每隔一段时间上报当前进度
            if (time() > $this->reportAt + $this->interval) {
                $this->reportAt = time();

                $this->fireEvent('progress', $line);
            }
        }
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
        foreach ($this->validateRows($this->rows) as $line => $errorMessages) {
            $row = $this->originalRows[$line];

            $this->errors[$line] = new ImportError(
                $row, $line, new RuntimeException(join('; ', $errorMessages))
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

    /**
     * 获取导入列配置信息与选项
     *
     * @param array $input
     * @return array
     */
    public function getColumnsOptions()
    {
        return [$this->fields, $this->rules, $this->remarks];
    }

    /**
     * 获取导入模板
     *
     * @param array $input
     * @return Template
     */
    public function getImportTemplate(array $input = []): Template
    {
        return new Template(...$this->getColumnsOptions());
    }

    /**
     * 获取可选列
     *
     * @param array $input
     * @return array<string, \QT\Import\Contracts\Dictionary>
     */
    public function getOptionalColumns(array $input = []): array
    {
        $optional = [];
        foreach ($this->optional as $field) {
            $dictionary = $this->getDictionary($field);

            if ($dictionary === null) {
                continue;
            }

            $optional[$field] = $dictionary;
        }

        return $optional;
    }
}
