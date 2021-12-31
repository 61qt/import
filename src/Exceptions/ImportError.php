<?php

namespace QT\Import\Exceptions;

use Throwable;
use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Expression;

/**
 * ImportError
 * @package QT\Import\Exceptions
 */
class ImportError extends RuntimeException
{
    protected $row;

    protected $line;

    /**
     * @param array $row
     * @param int $line
     * @param Throwable $previous
     */
    public function __construct(array $row, int $line, Throwable $previous)
    {
        $this->row  = $row;
        $this->line = $line;
        // 使用将原版错误进行包装
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    /**
     * 获取错误行内容
     * 
     * @param array $keys
     * @return array
     */
    public function getErrorRow($keys = []): array
    {
        if (empty($keys)) {
            return $this->row;
        }

        $result = [];
        foreach ($keys as $key) {
            $value = Arr::get($this->row, $key);
            // 将表达式变为空
            $result[] = is_object($value) && $value instanceof Expression
                ? null
                : $value;
        }

        return $result;
    }

    /**
     * 获取错误行号
     * 
     * @return int
     */
    public function getErrorLine()
    {
        return $this->line;
    }
}
