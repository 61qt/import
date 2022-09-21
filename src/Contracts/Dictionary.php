<?php

namespace QT\Import\Contracts;

/**
 * 字典接口
 *
 * key为excel里填写的值
 * value是插入到数据库的值
 * 
 * @package QT\Import\Contracts
 */
interface Dictionary
{
    /**
     * 检查key是否有对应的值
     *
     * @param string|int $key
     * @return bool
     */
    public function has(string | int $key): bool;

    /**
     * 获取key对应的值
     *
     * @param string|int $key
     * @return string|int|null
     */
    public function get(string | int $key): string | int | null;

    /**
     * 获取所有的字典内容
     *
     * @return array
     */
    public function all(): array;

    /**
     * 获取所有字典的key
     *
     * @return array
     */
    public function keys(): array;

    /**
     * 获取所有字典的values
     *
     * @return array
     */
    public function values(): array;
}
