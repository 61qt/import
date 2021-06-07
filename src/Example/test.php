<?php

use QT\Import\Contracts\Dictionary;

class MyDict implements Dictionary
{
        /**
     *  获取所有字典信息
     *
     * @return array
     */
    public function getDictionaries(): array
    {
        return [];
    }

    /**
     *  获取下拉对应字典信息
     *
     * @return array
     */
    public function getOptionalDictionaries(): array
    {
        return [];
    }
}