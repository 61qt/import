<?php

namespace QT\Import\Exceptions;

use Illuminate\Database\Query\Expression;

class ImportError extends \RuntimeException
{
    protected $row;

    protected $errorLine;

    public function __construct(
        $row,
        $errorLine,
        \Throwable $previous
    ) {
        $this->row       = $row;
        $this->errorLine = $errorLine;
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
            $value = array_get($this->row, $key);
            // 将表达式变为空
            $result[] = is_object($value) && $value instanceof Expression
                ? null
                : $value;
        }

        return $result;
    }

    public function getErrorLine()
    {
        return $this->errorLine;
    }
}
