<?php

namespace QT\Import\Exceptions;

use Throwable;
use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Expression;

class ImportError extends RuntimeException
{
    protected $row;

    protected $line;

    public function __construct(array $row, int $line, Throwable $previous)
    {
        $this->row  = $row;
        $this->line = $line;
        // 使用将原版错误进行包装
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    public function getErrorRow($keys = [])
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

    public function getErrorLine()
    {
        return $this->line;
    }
}
