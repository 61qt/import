<?php

namespace QT\Import\Readers;

use Iterator;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\RowIteratorInterface;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\XLSX\Options as XLSXOptions;

/**
 * OpenSpoutReader
 *
 * @package QT\Import
 */
class OpenSpoutReader implements Iterator
{
    /**
     * @var SheetInterface $sheet
     */
    protected $sheet;

    /**
     * @var RowIteratorInterface
     */
    protected $rows;

    /**
     * 构建excel rows读取器
     *
     * @param string $filename
     * @param array $options
     */
    public function __construct(protected string $filename, protected array $options)
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

        $this->sheet = $sheets->current();
        $this->rows  = $this->sheet->getRowIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->rows->rewind();
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
     * @return ?array
     */
    public function current(): ?array
    {
        $data  = [];
        $cells = $this->rows->current()->getCells();
        foreach ($cells as $cell) {
            $data[] = $cell->getValue();
        }

        return $data;
    }
}
