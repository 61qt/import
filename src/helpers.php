<?php

if (!function_exists('array_to_key')) {
    // 将数组内容增加分隔符\t之后组成key,避免如下问题
    // join('', ['aa', 'bb', 'cc']) === join('', ['aab', 'bcc'])
    function array_to_key($array)
    {
        return implode("\t", $array);
    }
}
