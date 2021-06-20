<?php

namespace QT\Import\Traits;

use RuntimeException;
use Box\Spout\Common\Type;
use Illuminate\Support\Arr;
use QT\Import\Exceptions\Error;
use Box\Spout\Reader\SheetInterface;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Reader\Common\Creator\ReaderFactory;

trait ParseXlsx
{
    /**
     * 可导入行数最大上限
     *
     * @var int
     */
    protected $maxRowQuantity = 2000;

    /**
     * 日期格式
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * 使用的sheet顺序索引
     *
     * @var int
     */
    protected $sheetIndex = 1;

    /**
     * 原始行数据
     *
     * @var array
     */
    protected $originalRows = [];

    /**
     * 超过最大行错误提示
     *
     * @var string
     */
    protected $maxRowErrorMessage = 'Excel 最大行数超过%d，请把行数减少至%d以内后，重新上传';

    /**
     * 解析Xlsx文件
     *
     * @param string $filename
     * @param string $type
     * @return \Generator;
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
     */
    protected function praseFile($filename, $type = Type::XLSX)
    {
        $reader = ReaderFactory::createFromType($type);
        $reader->open($filename);

        // 不跳过空行,不然会造成行号错误的情况
        if (method_exists($reader, 'setShouldPreserveEmptyRows')) {
            $reader->setShouldPreserveEmptyRows(true);
        }

        $line     = 0;
        $iterator = $this->getSheet($reader, $this->sheetIndex)->getRowIterator();
        foreach ($iterator as $row) {
            $row = $row->getCells();
            if ($line++ === 0) {
                // 处理列名顺序
                $fields = $this->getFields($row);
                continue;
            }

            // 行号从1开始计算,所以最大导入行数需要加1
            if ($line > $this->maxRowQuantity + 1) {
                throw new RuntimeException($this->getMaxRowQuantityError());
            }

            // 组装row,保证row的内容与导入模板设置的字段匹配
            $result   = [];
            $emptyRow = true;
            foreach ($fields as $index => $field) {
                $value = $row[$index] ?? '';

                if ($value !== '') {
                    $emptyRow = false;
                }

                $result[$field] = $value instanceof \DateTime
                    ? $value->format($this->dateFormat)
                    : trim($value);
            }

            // 整行都是空的就忽略
            if ($emptyRow) {
                continue;
            }

            // 冗余原始行数据
            $this->originalRows[$line] = $result;

            yield [$result, $line];
        }
    }

    /**
     * @param ReaderInterface $reader
     * @param int $index
     * @return SheetInterface
     * @throws Error
     */
    protected function getSheet(ReaderInterface $reader, $sheetIndex)
    {
        // 重置sheet迭代器
        $reader->getSheetIterator()->rewind();

        foreach ($reader->getSheetIterator() as $index => $sheet) {
            if ($sheetIndex === $index) {
                return $sheet;
            }
        }

        throw new RuntimeException("Sheet{$sheetIndex} 不存在");
    }

    /**
     * 根据首行数据匹配列名
     *
     * @param array $firstRow
     * @return array
     * @throws Error
     */
    protected function getFields($firstRow)
    {
        $results = [];
        $columns = array_flip($this->fields);
        foreach ($firstRow as $row) {
            $value = $row->getValue();
            // 剔除括号内的内容
            if (false !== strpos($value, '(')) {
                $value = Arr::first(explode('(', $value));
            }

            if (empty($columns[$value])) {
                throw new RuntimeException('导入模板与系统提供的模板不一致，请重新导入');
            }

            $results[] = $columns[$value];
        }

        return $results;
    }

    /**
     * 获取最大行报错信息
     *
     * @return string
     */
    protected function getMaxRowQuantityError()
    {
        return sprintf(
            $this->maxRowErrorMessage,
            $this->maxRowQuantity,
            $this->maxRowQuantity
        );
    }
}
