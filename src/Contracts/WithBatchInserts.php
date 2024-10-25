<?php

namespace QT\Import\Contracts;

/**
 * 批量写入
 *
 * @package QT\Import\Contracts
 */
interface WithBatchInserts
{
    /**
     * DB事务回调
     *
     * @return callable
     */
    public function transaction(): callable;

    /**
     * 批量插入
     *
     * @return void
     */
    public function insertDB();
}
