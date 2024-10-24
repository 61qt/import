<?php

namespace QT\Import;

use Iterator;
use RuntimeException;

/**
 * Rows
 *
 * @package QT\Import
 */
class Rows implements Iterator
{
    /**
     * excel列号
     * 
     * @var array
     */
    protected $columns;

    /**
     * @param Iterator $rows
     * @param callable $matchColumnsFn
     */
    public function __construct(protected Iterator $rows, protected $matchColumnsFn)
    {
        if (!is_callable($this->matchColumnsFn)) {
            throw new RuntimeException('matchColumnsFn must be callable');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->rows->rewind();

        $this->columns = call_user_func($this->matchColumnsFn, $this->rows);
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
        // 组装row,保证row的内容与导入模板设置的字段匹配
        $row   = [];
        $cells = $this->rows->current();
        foreach ($this->columns as $index => $column) {
            $value = '';
            if (!empty($cells[$index])) {
                $value = $cells[$index];

                if (is_scalar($value)) {
                    $value = trim(strval($value));
                }
            }

            $row[$column] = $value;
        }

        return $row;
    }
}
