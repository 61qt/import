<?php

namespace QT\Import\Contracts;

/**
 * 字典接口
 */
interface Dictionary
{
    /**
     *  获取所有字典信息
     *
     * @return array
     */
    public function getDictionaries(): array;

    /**
     *  获取下拉对应字典信息
     *
     * @return array
     */
    public function getOptionalDictionaries(): array;
}
