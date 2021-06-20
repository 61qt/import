<?php

namespace QT\Import;

use RuntimeException;
use QT\Import\Traits\Events;
use QT\Import\Traits\ParseXlsx;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Exceptions\ImportError;
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
     * 允许导入字段 (需要配置)
     *
     * @var array
     */
    protected $fields = [];

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
     * 开始处理异步导入任务
     *
     * @param string $filename
     * @param array $options
     */
    public function handle(string $filename, array $options = [])
    {
        $this->bootDictErrorMessages();
        $this->bootCheckTableDuplicated();

        ini_set('memory_limit', $this->memoryLimit);

        $this->options = $options;

        try {
            // 支持在任务开始前对option内容进行检查处理
            if (method_exists($this, 'beforeImport')) {
                $this->beforeImport($options);
            }
            // 处理excel文件
            $this->processFile($filename);
            // 从excel中提取的数据进行批量处理
            $this->checkAndFormatRows();
            // 插入到db
            $this->insertDB();

            // 错误行上报
            if (!empty($this->errors)) {
                $this->fireEvent('warning', $this->errors);
            }

            // 记录成功导入数量
            $this->fireEvent('complete', count($this->rows));
        } catch (\Throwable $e) {
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
            } catch (\Throwable$e) {
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
}
