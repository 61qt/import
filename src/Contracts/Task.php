<?php

namespace QT\Import\Contracts;

use Throwable;
use Serializable;

interface Task extends Serializable
{
    /**
     * 导入任务完成
     * 
     * @param int $count
     */
    public function complete(int $success);

    /**
     * 导入任务失败
     * 
     * @param Throwable $e
     */
    public function failed(Throwable $e);

    /**
     * 导入行部分失败
     * 
     * @param array<\QT\Import\Exceptions\ImportError> $errors
     */
    public function reportErrors(array $errors);

    /**
     * 上报进度
     * 
     * @param int $progress
     */
    public function reportProgress(int $progress);
}
