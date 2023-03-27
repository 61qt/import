<?php

namespace QT\Import\Exceptions;

use DateTime;
use Throwable;
use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Expression;

/**
 * RowError
 *
 * @package QT\Import\Exceptions
 */
class RowError extends RuntimeException implements ImportException
{
    /**
     * 错误行数据
     *
     * @var array
     */
    protected $row;

    /**
     * 错误行号
     *
     * @var int
     */
    protected $errLine;

    /**
     * @param array $row
     * @param int $errLine
     * @param Throwable $previous
     */
    public function __construct(array $row, int $errLine, Throwable $previous)
    {
        $this->row     = $row;
        $this->errLine = $errLine;
        // 使用将原版错误进行包装
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    /**
     * 获取错误行内容
     *
     * @param array $keys
     * @return array
     */
    public function getErrorRow(array $keys = []): array
    {
        if (empty($keys)) {
            $keys = array_keys($this->row);
        }

        $result = [];
        foreach ($keys as $key) {
            $value = Arr::get($this->row, $key);

            if (is_object($value)) {
                if ($value instanceof Expression) {
                    $value = null;
                } elseif ($value instanceof DateTime) {
                    $value = $value->format('Y/m/d');
                } elseif (method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } else {
                    $value = null;
                }
            }

            // 将表达式变为空
            $result[] = $value;
        }

        return $result;
    }

    /**
     * 获取错误行号
     *
     * @return int
     */
    public function getErrorLine(): int
    {
        return $this->errLine;
    }
}
