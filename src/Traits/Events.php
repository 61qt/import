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
     * 上次同步生成进度时间
     *
     * @var int
     */
    protected $reportAt;

    /**
     * 上报间隔时间
     *
     * @var int
     */
    protected $interval = 3;

    /**
     * 导入开始前触发
     *
     * @param array $input
     */
    public function beforeImport()
    {
        // do something
    }

    /**
     * 导入完成时触发
     *
     * @param array $successful 导入成功行
     * @param array $fail 导入失败行
     * @return mixed
     */
    public function afterImport(array $successful, array $fail)
    {
        // do something
    }

    /**
     * 导入时触发
     *
     * @param int $count
     * @return void
     */
    public function onReport(int $count)
    {
        // do something
    }

    /**
     * 导入执行失败时触发
     *
     * @param Throwable $exception
     * @return void
     */
    public function onFailed(Throwable $exception)
    {
        throw $exception;
    }
}
