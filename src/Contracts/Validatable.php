<?php

namespace QT\Import\Contracts;

/**
 * 字典接口
 *
 * key为excel里填写的值
 * value是插入到数据库的值
 * 
 * Validatable
 * @package QT\Import\Contracts
 */
interface Validatable
{
    /**
     * 批量校验
     *
     * @param array $rows
     * @param array $customAttributes
     * @return bool
     */
    public function validate($rows, $customAttributes = []): bool;

    /**
     * 获取错误行
     *
     * @return array
     */
    public function errors(): array;
}
