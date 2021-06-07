<?php

namespace QT\Import\Traits;

use Illuminate\Support\Arr;
use QT\Import\Exceptions\Error;
use Illuminate\Database\Query\Expression;

/**
 * 检查同一个列表内数据是否重复
 */
trait CheckTableDuplicated
{
    /**
     * 已存在的字段值
     *
     * field => [values...]
     * @var array
     */
    private $existingFieldValues = [];

    /**
     * @var array
     */
    private $uniqueKeys = [];

    /**
     * 获取excel内需要进行唯一性检查的字段
     *
     * @return array
     */
    protected function getExcelUniqueFields(): array
    {
        return [];
    }

    /**
     * 初始化需要进行检查唯一性的字段
     */
    protected function bootCheckTableDuplicated()
    {
        $uniques = $this->getExcelUniqueFields();

        foreach ($uniques as $fields) {
            if (!is_array($fields)) {
                $fields = [$fields];
            }

            $key = join('.', $fields);

            $this->uniqueKeys[$key]          = $fields;
            $this->existingFieldValues[$key] = [];
        }
    }

    /**
     * @param $data
     * @param $line
     * @return int 冲突行号
     */
    public function checkTableDuplicated($data, $line)
    {
        foreach ($this->uniqueKeys as $key => $fields) {
            if (!Arr::has($data, $fields)) {
                continue;
            }

            $values = $this->getAndCheckValues($data, $fields);

            if ($values === false) {
                continue;
            }

            $value = array_to_key($values);

            if (!array_key_exists($value, $this->existingFieldValues[$key])) {
                // 冗余字段已存在的值
                $this->existingFieldValues[$key][$value] = $line;
                continue;
            }

            $attributes   = $this->getCustomAttributes();
            $conflictLine = $this->existingFieldValues[$key][$value];
            $errField     = join(', ', array_map(function ($field) use ($attributes) {
                return $attributes[$field] ?? $field;
            }, $fields));

            throw new Error(sprintf("%s 错误: 原表第%s行 与 第%s行 重复,请确保数据表内的数据为唯一的", $errField, $line, $conflictLine));
        }
    }

    protected function getAndCheckValues($data, $fields)
    {
        $values = [];
        // 多个值作为唯一校验时，其中一个没有填写不检查唯一性
        foreach ($fields as $field) {
            if ($data[$field] === null || $data[$field] === '' || $data[$field] instanceof Expression) {
                return false;
            }
            $values[] = $data[$field];
        }

        return $values;
    }
}
