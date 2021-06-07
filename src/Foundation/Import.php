<?php

namespace QT\Import\Foundation;

use QT\Import\Exceptions\Error;
use QT\Import\Traits\ParseXlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Exceptions\ImportError;
use QT\Import\Foundation\ImportTemplate;
use QT\Import\Traits\CheckTableDuplicated;

class Import
{
    use ParseXlsx;
    use RowsValidator;
    use CheckAndFormat;
    use CheckTableDuplicated;

    // excel单格字符长度不允许超过32767
    const MAX_CHARACTERS_PER_CELL = 32767;

    /**
     * 外部输入参数
     */
    protected $input = [];

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
     * 错误信息 excel 文件路径
     *
     * @var string
     */
    protected $errorFilename = '';

    /**
     * 构造函数不允许传参，保证从服务容器中生成时不会出错
     */
    public function __construct()
    {
        $this->bootCheckTableDuplicated();
    }

    protected function beforeImport()
    {

    }

    /**
     * 开始处理异步导入任务
     *
     * @param string $filename
     * @param array $input
     */
    public function handle($filename, $input = [])
    {
        $this->input = $input;

        ini_set('memory_limit', $this->memoryLimit);

        try {
            // 支持在任务开始前对input与task内容进行检查处理
            $this->beforeImport();
            // 处理excel文件
            $this->processFile($filename);
            // 从excel中提取的数据进行批量处理
            $this->checkAndFormatRows($this->rows);

            DB::transaction(function () {
                $this->insertDB();
            });

            if (!empty($this->errors)) {
                $this->reportErrors($this->errors);
            }

        } catch (\Throwable $e) {
            // 记录错误信息
            Log::error($e);
        } finally {
            $this->taskFinished();
        }
    }

    /**
     * 处理上传文件
     *
     * @param $file
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
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
            } catch (\Throwable $e) {
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
    protected function checkAndFormatRows($rows)
    {
        // 批量进行唯一索引检查,防止唯一索引冲突
        foreach ($this->validateRows($rows) as $line => $errorMessages) {
            $row = $this->originalRows[$line];

            $this->errors[$line] = new ImportError(
                $row, $line, new Error(join('; ', $errorMessages))
            );

            unset($this->rows[$line]);
        }

        // 数据校验完成后,清空冗余数据,释放内存
        $this->originalRows = [];
    }

    /**
     * 批量插入
     */
    protected function insertDB()
    {
    }

    /**
     * 上报错误信息
     *
     * @param array $errors
     * @return string
     */
    protected function reportErrors($errors)
    {
        $filename = "/tmp/{$this->getUniqueId()}.xlsx";
        $template = new ImportTemplate(
            array_merge(['err_msg' => '错误原因'], $this->fields),
            array_merge(['err_msg' => 'required'], $this->rules),
            array_merge(['err_msg' => '根据错误原因修改后,可以删除错误原因一列,重新上传'], $this->fieldRemarks)
        );

        try {
            $template->generateColumns();
            $template->fillSimpleData($this->formatErrors($errors));
            $template->save($filename);
        } finally {
            $this->errorFilename = $filename;
        }
    }

    /**
     * 获取唯一 id 放置错误文件信息
     *
     * @param string $id
     * @return string
     */
    protected function getUniqueId($id = 0): string
    {
        $id = $id ?: uniqid();
        return sprintf('%s-%s-%d', config('app.name'), $id, time());
    }

    /**
     * 上报错误信息
     *
     * @param array $errors
     * @return \Generator
     */
    protected function formatErrors($errors)
    {
        $fields = array_keys($this->fields);

        foreach ($errors as $error) {
            $previous = $error->getPrevious();
            // 业务层错误如实返回
            if ($previous instanceof Error) {
                $errMsg = $previous->getMessage();
            } else {
                $errMsg = $previous->getMessage();

                Log::error($previous);
            }

            // 按照fields顺序取出字段,防止row内容被打乱导致与原表数据不符
            $row = $error->getErrorRow($fields);
            yield array_merge(
                [mb_substr($errMsg, 0, self::MAX_CHARACTERS_PER_CELL)], $row
            );
        }
    }

    /**
     * 任务结束处理逻辑
     *
     * @return void
     */
    protected function taskFinished()
    {
    }
}
