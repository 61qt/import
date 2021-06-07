<?php

namespace QT\Import\Exceptions;

/**
 * 错误返回异常
 * @author barbery
 * @package App\Exceptions
 */
class Error extends \RuntimeException
{
    private $_data         = [];
    private $_type         = 'json';
    const DEFAULT_ERR_CODE = 1000;

    public function __construct($message)
    {
        $this->code    = self::DEFAULT_ERR_CODE;
        $this->message = $message;
    }

    public function setData($data)
    {
        $this->_data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->_data;
    }

    public function setContentType($type)
    {
        $this->_type = $type;

        return $this;
    }

    public function getContentType()
    {
        return $this->_type;
    }
}
