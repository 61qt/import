<?php

namespace QT\Import\Contracts;

use Illuminate\Database\Query\Builder;

/**
 * Template
 * @package QT\Import\Contracts
 */
interface Template
{
    /**
     * 设置导入sheet
     *
     * @param int $index
     * @param string|null $title
     */
    public function setImportSheet(int $index, string $title = null);

    /**
     * 设置导入列名
     *
     * @param array $columns
     * @param array $rules
     * @param array $remarks
     */
    public function setFirstColumn(array $columns, array $rules = [], array $remarks = []);

    /**
     * 设置允许使用下拉选项的列
     *
     * @param array<string, Dictionary> $dictionaries
     * @param string|null $title
     */
    public function setOptionalColumns(array $dictionaries, string $title = null);

    /**
     * 在excel第三个sheet中生成示例(自动生成和模板一样的首行)
     *
     * @param array $example
     */
    public function setExampleSheet(array $example);

    /**
     * 给导入模板填入基础数据
     *
     * @param Builder|iterable $source
     * @param int $sheetIndex
     * @param int $startColumn
     * @param int $startRow
     */
    public function fillSimpleData($source, int $sheetIndex = 0, int $startColumn = 0, int $startRow = 2);

    /**
     * 将文件保存到指定位置
     *
     * @param string $filename
     */
    public function save(string $filename);
}
