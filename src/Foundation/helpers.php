<?php

use Illuminate\Support\Arr;

if (!function_exists('array_to_key')) {
    // 将数组内容进行hash之后组成key,避免如下问题
    // join('', ['aa', 'bb', 'cc']) === join('', ['aab', 'bcc'])
    function array_to_key($array)
    {
        // 部分地方使用only函数获取的结构包含key
        // 直接json化会导致与value组成的结果集不一致
        // 所以去除key,用value组成新的数组
        // encode([1, 2, 3]) != encode(['a' => 1, 'b' => 2, 'c' => 3])
        return implode("\t", array_values($array));
    }
}

if (! function_exists('array_get')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
}


if (! function_exists('array_first')) {
    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param  array  $array
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    function array_first($array, callable $callback = null, $default = null)
    {
        return Arr::first($array, $callback, $default);
    }
}
