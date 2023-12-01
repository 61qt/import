<?php

namespace QT\Import;

use Iterator;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\SheetInterface;
use QT\Import\Exceptions\FieldException;
use OpenSpout\Reader\RowIteratorInterface;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\XLSX\Options as XLSXOptions;

/**
 * Rows
 *
 * @package QT\Import
 */
class Rows implements Iterator
{
    public const STRICT_MODE   = 1;
    public const TOLERANT_MODE = 2;

    /**
     * @var SheetInterface $sheet
     */
    protected $sheet;

    /**
     * @var RowIteratorInterface
     */
    protected $rows;

    /**
     * @var array
     */
    protected $fields;

    /**
     * 校验模式
     *
     * @var bool
     */
    protected $mode = Rows::TOLERANT_MODE;

    /**
     * 默认跳过的行数
     *
     * @var int
     */
    protected $startRow = 0;

    /**
     * 字段错误提示语
     *
     * @var string
     */
    protected $fieldErrorMsg = '导入模板与系统提供的模板不一致，请重新导入';

    /**
     * @param SheetInterface $sheet
     * @param array $fields
     * @param array $options
     */
    public function __construct(
        SheetInterface $sheet,
        array $fields,
        protected array $options = [],
    ) {
        if (isset($this->options['start_row'])) {
            $this->startRow = $this->options['start_row'];
        }

        if (isset($this->options['mode'])) {
            $this->mode = $this->options['mode'];
        }

        $this->sheet  = $sheet;
        $this->rows   = $sheet->getRowIterator();
        $this->fields = $this->formatFields($this->rows, $fields);
    }

    /**
     * 构建excel rows读取器
     *
     * @param string $filename
     * @param Task $task
     * @param array $input
     * @param array $options
     * @return self
     */
    public static function createFrom(string $filename, Task $task, array $input = [], array $options = [])
    {
        $readerOptions = new XLSXOptions();

        if (isset($options['read'])) {
            foreach ($options['read'] as $name => $value) {
                $readerOptions->{$name} = $value;
            }
        }
        $reader = new XLSXReader($readerOptions);
        $reader->open($filename);
        $sheets = $reader->getSheetIterator();
        $sheets->rewind();
        // 允许指定sheet
        $skip = $options['sheet_index'] ?? 0;

        while ($skip-- > 0) {
            $sheets->next();
        }

        return new static($sheets->current(), $task->getFields($input), array_merge($options, [
            'mode' => $task->getFieldsCheckMode($input),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->rows->rewind();

        for ($i = 0; $i < $this->startRow; $i++) {
            $this->rows->next();
        }

        // 默认跳过首行内容
        $this->next();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->rows->valid();
    }

    /**
     * 游标到下一行
     *
     * @return void
     */
    public function next(): void
    {
        $this->rows->next();
    }

    /**
     * 获取当前行的key
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->rows->key();
    }

    /**
     * 当前行数据
     *
     * @return array
     */
    public function current(): array
    {
        /** @var Row $row */
        $row = $this->rows->current()->getCells();

        // 组装row,保证row的内容与导入模板设置的字段匹配
        $result = [];
        foreach ($this->fields as $index => $field) {
            $value = '';
            if (!empty($row[$index])) {
                $value = $row[$index]->getValue();

                if (is_scalar($value)) {
                    $value = trim(strval($value));
                }
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * 根据首行数据匹配列名
     *
     * @param RowIteratorInterface $rows
     * @param array $fields
     * @throws Error
     * @return array
     */
    protected function formatFields(RowIteratorInterface $rows, array $fields): array
    {
        $results = [];
        $count   = count($fields);
        $columns = array_flip($fields);
        $rows->rewind();

        for ($i = 0; $i < $this->startRow; $i++) {
            $rows->next();
        }

        foreach ($rows->current()->getCells() as $index => $value) {
            // 超过最大列数后不再取值
            if ($index === $count) {
                break;
            }

            $value = $value->getValue();
            $pos   = strpos($value, '(');
            // 剔除括号内的内容
            if (false !== $pos) {
                $value = substr($value, 0, $pos);
            }

            if (empty($columns[$value])) {
                throw new FieldException($this->fieldErrorMsg);
            }

            $results[$index] = $columns[$value];
        }

        // 严格模式下字段必须完全匹配
        if ($this->mode === Rows::STRICT_MODE && count($columns) !== count($results)) {
            throw new FieldException($this->fieldErrorMsg);
        }

        return $results;
    }
}
