<?php

namespace QT\Import\Contracts;

/**
 * WithMultipleSheets
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
