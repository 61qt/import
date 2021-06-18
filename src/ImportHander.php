<?php

namespace QT\Import\Foundation;

use RuntimeException;
use QT\Import\Contracts\Task;
use QT\Import\Exceptions\Error;
use QT\Import\Traits\ParseXlsx;
use Illuminate\Support\Facades\DB;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Exceptions\ImportError;
use QT\Import\Traits\CheckTableDuplicated;

class ImportHander
{
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
     * 构造函数不允许传参，保证从服务容器中生成时不会出错
     */
    public function __construct()
    {
        $this->bootCheckTableDuplicated();
    }

    /**
     * 开始处理异步导入任务
     *
     * @param Task $task
     * @param string $filename
     */
    public function handle(Task $task, string $filename)
    {
        ini_set('memory_limit', $this->memoryLimit);

        try {
            // 支持在任务开始前对input与task内容进行检查处理
            // $this->beforeImport($task);
            // 处理excel文件
            $this->processFile($filename);
            // 从excel中提取的数据进行批量处理
            $this->checkAndFormatRows();

            DB::transaction(function () {
                $this->insertDB();
            });

            // 错误行上报
            if (!empty($this->errors)) {
                $task->reportErrors($this->errors);
            }

            // 记录成功导入数量
            $task->complete(count($this->rows));
        } catch (\Throwable$e) {
            // 记录错误信息
            $task->failed($e);
        }
    }

    /**
     * 处理上传文件
     *
     * @param $file
     */
    protected function processFile($file)
    {
        foreach ($this->parseXlsx($file, $this->fields) as [$row, $line, $fields]) {
            try {
                $row = $this->checkAndFormatRow($row, $line, $fields);

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
        }
    }

    /**
     * 检查并格式化一行数据
     *
     * @param array $data
     * @param int $line
     * @param array $fields
     * @return mixed
     * @throws Error
     */
    protected function checkAndFormatRow($data, $line, $fields)
    {
        list($result, $line, $fields) = $this->formatRow(
            ...$this->checkRow($data, $line, $fields)
        );

        // 进行唯一性检查,保证唯一索引字段在excel内不会有重复
        $this->checkTableDuplicated($result, $line);

        return $result;
    }

    /**
     * 批量处理行信息
     *
     * @param array @rows
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
        // Insert or update
    }
}
