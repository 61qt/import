<?php

namespace QT\Import\Contracts;

/**
 * 支持多sheet导入
 * 
 * @package QT\Import\Contracts
 */
interface WithMultipleSheets
{
    /**
     * 获取需要导入的sheet
     *
     * @return array
     */
    public function sheets(): array;
}
