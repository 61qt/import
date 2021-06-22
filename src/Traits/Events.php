<?php

namespace QT\Import\Traits;

use Illuminate\Contracts\Events\Dispatcher;

trait Events
{
    /**
     * 事件触发器.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    /**
     * 监听批量导入完成事件
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public function complete($callback)
    {
        $this->registerModelEvent('complete', $callback);
    }

    /**
     * 监听批量导入执行失败事件
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public function failed($callback)
    {
        $this->registerModelEvent('failed', $callback);
    }

    /**
     * 监听批量导入进度更新事件
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public function progress($callback)
    {
        $this->registerModelEvent('progress', $callback);
    }

    /**
     * 注册事件监听者到调度器.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @return void
     */
    protected function registerModelEvent($event, $callback)
    {
        if (isset($this->dispatcher)) {
            $name = static::class;

            $this->dispatcher->listen("qt.{$event}:{$name}", $callback);
        }
    }

    /**
     * 释放事件
     *
     * @param  string  $event
     * @return mixed
     */
    protected function fireEvent($event, ...$params)
    {
        if (!isset($this->dispatcher)) {
            return true;
        }

        return $this->dispatcher->dispatch("qt.{$event}:".static::class, $params);
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function setEventDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->dispatcher = null;
    }
}
