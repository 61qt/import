<?php

namespace QT\Import;

use QT\Import\Contracts\Dictionary as ContractDictionary;

/**
 * Import Dictionary
 *
 * @package QT\Import
 */
class Dictionary implements ContractDictionary
{
    /**
     * @param array $maps
     */
    public function __construct(protected array $maps)
    {
    }

    /**
     * 检查key是否有对应的值
     *
     * @param string|int $key
     * @return bool
     */
    public function has(string | int $key): bool
    {
        return isset($this->maps[$key]);
    }

    /**
     * 获取key对应的值
     *
     * @param string|int $key
     * @return string|int|null
     */
    public function get(string | int $key): string | int | null
    {
        return $this->has($key) ? $this->maps[$key] : null;
    }

    /**
     * 获取所有的字典内容
     *
     * @return array
     */
    public function all(): array
    {
        return $this->maps;
    }

    /**
     * 获取所有字典的key
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->maps);
    }

    /**
     * 获取所有字典的values
     *
     * @return array
     */
    public function values(): array
    {
        return array_values($this->maps);
    }
}
