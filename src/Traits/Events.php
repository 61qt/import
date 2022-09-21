<?php

namespace QT\Import\Traits;

use Throwable;

/**
 * 触发器
 *
 * @package QT\Import\Traits
 */
trait Events
{
    /**
     * 导入开始前触发
     *
     * @param array $input
     */
    public function beforeImport(array $input)
    {
        // do something
    }

    /**
     * 导入完成时触发
     *
     * @param  array $successful 导入成功行
     * @param  array $failed     导入失败行
     * @return void
     */
    public function afterImport(array $successful, array $fail)
    {
        // do something
    }

    /**
     * 导入时触发
     *
     * @param  int $count
     * @return void
     */
    public function onReport(int $count)
    {
        // do something
    }

    /**
     * 导入执行失败时触发
     *
     * @param  Throwable $exception
     * @return void
     */
    public function onFailed(Throwable $exception)
    {
        throw $exception;
    }
}
