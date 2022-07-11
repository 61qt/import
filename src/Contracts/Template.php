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
     * 设置导入列名
     *
     * @param array $columns
     * @param array $rules
     * @param array $remarks
     */
    public function setFirstColumn(array $columns, array $rules = [], array $remarks = []);

    /**
     * 生成示例
     *
     * @param array $example
     */
    public function setExample(array $example);

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
