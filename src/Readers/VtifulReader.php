<?php

namespace QT\Import\Readers;

use Iterator;
use Vtiful\Kernel\Excel;

/**
 * VtifulReader
 *
 * @package QT\Import
 */
class VtifulReader implements Iterator
{
    /**
     * Xlsx Reader
     *
     * @var Excel
     */
    protected $reader;

    /**
     * 当前行号
     *
     * @var int
     */
    protected $currentLine = 0;

    /**
     * 当前读取的行内容
     *
     * @var array
     */
    protected $currentData = [];

    /**
     * 是否还能读取后续内容
     *
     * @var bool
     */
    protected $isValid = true;

    /**
     * 构建excel rows读取器
     *
     * @param string $filename
     * @param array $options
     */
    public function __construct(protected string $filename, protected array $options)
    {
        $this->reader = new Excel(['path' => '/']);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $sheets = $this->reader->openFile($this->filename)->sheetList();
        // 允许指定sheet
        $this->reader->openSheet($sheets[$this->options['sheet_index'] ?? 0]);

        $this->currentLine = 0;
        $this->currentData = null;

        $this->next();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->isValid;
    }

    /**
     * 游标到下一行
     *
     * @return void
     */
    public function next(): void
    {
        $this->currentLine++;
        $this->currentData = $this->reader->nextRow();

        if ($this->currentData === null) {
            $this->isValid = false;
        }
    }

    /**
     * 获取当前行的key
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->currentLine;
    }

    /**
     * 当前行数据
     *
     * @return ?array
     */
    public function current(): ?array
    {
        return $this->currentData;
    }
}
