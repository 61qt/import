<?php

namespace QT\Import;

use Iterator;
use Box\Spout\Common\Entity\Row;
use Box\Spout\Reader\SheetInterface;
use Box\Spout\Reader\IteratorInterface;
use QT\Import\Exceptions\FieldException;

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
     * @var IteratorInterface
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
    protected $mode;

    /**
     * @var string
     */
    protected $fieldErrorMsg = '导入模板与系统提供的模板不一致，请重新导入';

    /**
     * @param SheetInterface $sheet
     * @param array $fields
     * @param int $mode
     */
    public function __construct(
        SheetInterface $sheet,
        array $fields,
        int $mode = Rows::TOLERANT_MODE
    ) {
        $this->sheet  = $sheet;
        $this->mode   = $mode;
        $this->rows   = $sheet->getRowIterator();
        $this->fields = $this->formatFields($this->rows, $fields);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->rows->rewind();
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
     * @return integer
     */
    public function key(): int
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
     * @param IteratorInterface $rows
     * @param array $fields
     * @return array
     * @throws Error
     */
    private function formatFields(IteratorInterface $rows, array $fields): array
    {
        $results = [];
        $columns = array_flip($fields);

        $rows->rewind();
        foreach ($rows->current()->getCells() as $value) {
            $value = $value->getValue();
            $pos   = strpos($value, '(');
            // 剔除括号内的内容
            if (false !== $pos) {
                $value = substr($value, 0, $pos);
            }

            if (empty($columns[$value])) {
                throw new FieldException($this->fieldErrorMsg);
            }

            $results[] = $columns[$value];
        }

        // 严格模式下字段必须完全匹配
        if ($this->mode === Rows::STRICT_MODE && count($columns) !== count($results)) {
            throw new FieldException($this->fieldErrorMsg);
        }

        return $results;
    }
}
