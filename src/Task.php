<?php

namespace QT\Import;

use Iterator;
use Throwable;
use QT\Import\Traits\Events;
use QT\Import\Contracts\Template;
use QT\Import\Traits\CheckMaxRow;
use QT\Import\Exceptions\RowError;
use QT\Import\Traits\WithTemplate;
use Illuminate\Container\Container;
use QT\Import\Readers\VtifulReader;
use QT\Import\Traits\RowsValidator;
use QT\Import\Traits\CheckAndFormat;
use QT\Import\Contracts\WithBatchInserts;
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
    protected $memoryLimit = '1280M';

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
     * 是否捕获行错误
     * 可以在同步导入时直接抛出错误不执行后续逻辑
     * 异步任务执行时请开启该标识,防止异常中断任务
     *
     * @var bool
     */
    protected $catch = true;

    /**
     * 读取的sheet位置
     *
     * @var integer
     */
    protected $sheetIndex = 0;

    /**
     * 获取导入字段
     *
     * @return array
     */
    abstract public function getFields(): array;

    /**
     * 设置文件读取逻辑
     *
     * eq: new VtifulReader($filename, [
     *     'sheet_index' => $this->sheetIndex,
     * ])
     *
     * @param string $filename
     * @return Iterator
     */
    abstract public function getFileReader(string $filename): Iterator;

    /**
     * 设置字段匹配逻辑
     *
     * eq:
     * new MatchColumns($this->getFields(), [
     *     'start_row' => 0,
     *     'mode'      => MatchColumns::TOLERANT_MODE,
     * ])
     *
     * @return callable
     */
    abstract public function getMatchColumnFn(): callable;

    /**
     * 读取导入文件
     *
     * @param string $filename
     * @param array $input
     * @return array
     */
    public static function read(string $filename, array $input = [])
    {
        /** @var Task $task */
        $task   = Container::getInstance()->make(static::class);
        $rows   = new Rows($task->getFileReader($filename), $task->getMatchColumnFn());
        $result = $task->init($input)->handle($rows);

        if ($task instanceof WithBatchInserts) {
            call_user_func($task->transaction(), fn () => $task->insertDB($result));
        }

        return $result;
    }

    /**
     * 获取可导入模板文件
     *
     * @param array $input
     * @return Template
     */
    public static function template(array $input = []): Template
    {
        /** @var Task $task */
        $task     = Container::getInstance()->make(static::class);
        $fields   = $task->init($input)->getFields();
        $template = $task->getImportTemplate($input['driver'] ?? $task->templateDriver);

        $template->setImportSheet($task->sheetIndex);
        $template->setFirstColumn($fields, $task->rules, $task->remarks);
        $template->setColumnFormat($task->formatColumns);
        $template->setDictionaries($task->getDictionaries());
        $template->setOptionalColumns($task->optional);

        foreach ($task->ruleComments as $rule => $comment) {
            $template->setRuleComment($rule, $task->getCommentCallback($comment));
        }

        foreach ($task->ruleStyles as $rule => $style) {
            $template->setRuleStyle($rule, $style);
        }

        return $template;
    }

    /**
     * 初始化导入任务
     *
     * @param array $input
     * @return self
     */
    public function init(array $input = []): self
    {
        return $this;
    }

    /**
     * 读取导入数据
     *
     * @param iterable $rows
     * @return array
     */
    public function handle(iterable $rows): ?array
    {
        ini_set('memory_limit', $this->memoryLimit);

        // 初始化错误字段展示名称
        $this->displayNames = array_merge($this->getFields(), $this->displayNames);
        // 初始化错误信息
        $this->bootDictErrorMessages();
        $this->bootCheckTableDuplicated();
        $this->bootFieldDateFormats();

        try {
            // 导入任务初始化
            $this->beforeImport();
            // 处理行内容
            $this->processRows($rows);
            // 触发任务完成事件
            $this->afterImport($this->rows, $this->errors);
            // 如果不写入db,返回导入的数据
            if (!$this instanceof WithBatchInserts) {
                return $this->rows;
            }
        } catch (Throwable $e) {
            $this->onFailed($e);
        }

        return null;
    }

    /**
     * 处理上传文件
     *
     * @param iterable $rows
     */
    protected function processRows(iterable $rows)
    {
        $currentLine = 1;
        foreach ($rows as $line => $row) {
            // 整行都是空的就忽略
            if (empty($row)) {
                continue;
            }

            // 根据具体有数据的行来判断
            $this->checkMaxRow($currentLine++);

            try {
                $data = $this->checkAndFormatRow($row, $line);

                if (empty($data)) {
                    continue;
                }
                // 进行唯一性检查,保证唯一索引字段在excel内不会有重复
                $this->checkTableDuplicated($data, $line);

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
     * @param array $row
     * @param int $line
     * @throws ValidationException
     * @return array
     */
    protected function checkAndFormatRow(array $row, int $line): array
    {
        $row = $this->checkRow($this->formatRow($row));

        foreach ($row as $field => $value) {
            // 过滤空值
            if ($value !== '') {
                continue;
            }

            if ($this->useDefault) {
                // 批量插入时需要设置默认值
                $row[$field] = $this->getDefaultValue($field);
            } else {
                // 更新信息时保留原记录
                unset($row[$field]);
            }
        }

        return $row;
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
}
