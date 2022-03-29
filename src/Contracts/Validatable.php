<?php

namespace QT\Import\Contracts;

/**
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
