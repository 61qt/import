<?php

namespace QT\Import\Rules;

use Throwable;
use RuntimeException;
use QT\Import\Contracts\Validatable;

/**
 * 自定义校验规则
 *
 * Class CustomRule
 *
 * eq:
 * new CustomRule([$this, 'importDataValidator'])
 * new CustomRule(function ($row, $line) {
 *     if (empty($row['email'])) {
 *         throw new RuntimeException('邮箱为空');
 *     }
 * })
 *
 * @package QT\Import\Rules
 */
class CustomRule implements Validatable
{
    /**
     * 错误行
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Undocumented function
     */
    public function __construct(protected $fn)
    {
        if (!is_callable($this->fn)) {
            throw new RuntimeException('必须传入回调函数');
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param array $rows
     * @param array $displayNames
     * @return boolean
     */
    public function validate($rows, $displayNames = []): bool
    {
        foreach ($rows as $line => $row) {
            try {
                call_user_func($this->fn, $row, $line);
            } catch (Throwable $e) {
                if (empty($this->errors[$line])) {
                    $this->errors[$line] = [];
                }

                $this->errors[$line][] = $e->getMessage();
            }
        }

        return empty($this->errors);
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
