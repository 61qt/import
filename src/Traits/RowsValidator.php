<?php

namespace QT\Import\Traits;

use QT\Import\Contracts\Validatable;

/**
 * 导入行批量校验
 *
 * @package QT\Import\Traits
 */
trait RowsValidator
{
    /**
     * 获取数据库内需要进行唯一性检查的字段
     *
     * @return array
     */
    protected function getRowsRules(): array
    {
        return [];
    }

    /**
     *  将需要导入的结果集进行批量检查
     *
     * @param array $rows
     * @return array
     */
    protected function validateRows(array $rows): array
    {
        $errors = [];
        foreach ($this->getRowsRules() as $rule) {
            if (!$rule instanceof Validatable) {
                continue;
            }

            if ($rule->validate($rows, $this->displayNames)) {
                continue;
            }

            // 将错误行整合,保证一行内全部错误信息可以一次性返回
            foreach ($rule->errors() as $line => $errorMessages) {
                if (empty($errors[$line])) {
                    $errors[$line] = [];
                }

                $errors[$line] = array_merge($errors[$line], $errorMessages);
            }
        }

        return $errors;
    }
}
