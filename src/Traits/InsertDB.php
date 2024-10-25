<?php

namespace QT\Import\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * InsertDB
 *
 * @package QT\Import\Traits
 */
trait InsertDB
{
    /**
     * 是否启用事务
     *
     * @var bool
     */
    protected $useTransaction = true;

    /**
     * 启用DB事务
     *
     * @return callable
     */
    public function transaction(): callable
    {
        if (!empty($this->model) && is_subclass_of($this->model, Model::class)) {
            /** @var Connection $connection */
            $connection = $this->model::query()->getConnection();
        }

        // 插入到db
        if (empty($connection) || !$this->useTransaction) {
            return fn ($callback) => call_user_func($callback);
        }

        return fn ($callback) => $connection->transaction($callback);
    }

    /**
     * 批量插入
     *
     * @return void
     */
    public function insertDB()
    {
    }
}
