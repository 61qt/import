<?php

namespace QT\Import\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

abstract class ValidateModels implements Validatable
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * 忽视掉满足条件的字段
     *
     * @var array
     */
    protected $ignoreFields = [];

    /**
     * 默认筛选条件,在给每一行生成sql时中自动填充
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * 字段别名
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * 错误内容
     *
     * @var array
     */
    protected $errors = [];

    /**
     * 校验失败后提示信息
     *
     * @var array
     */
    protected $messages = [];

    /**
     * 默认错误信息
     *
     * @var string
     */
    protected $defaultErrorMessage = '校验错误,请重新填写';

    /**
     * 根据指定字段去database中获取数据,通过检查数据库数据判断导入数据是否可用
     *
     * new ValidateModels(
     *     Model::query(),
     *     ['id', 'email', ['id_number', 'user_type']],
     *     ['user_type' => 7],
     *     ['id']
     * )
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $attributes
     * @param array $wheres
     * @param array $ignoreFields
     * @param array $aliases
     * @param array $messages
     */
    public function __construct(
        Builder $query,
        array $attributes,
        array $wheres = [],
        array $ignoreFields = [],
        array $aliases = [],
        array $messages = []
    ) {
        $this->query        = $query;
        $this->wheres       = $wheres;
        $this->ignoreFields = $ignoreFields;
        $this->aliases      = $aliases;
        $this->messages     = $messages;

        foreach ($attributes as $key => $fields) {
            if (!is_array($fields)) {
                $fields = [$fields];
            }

            if (is_int($key)) {
                $key = Arr::first($fields);
            }

            // 获取字段别名与model中实际字段名 [excel表内名 => model实际字段名]
            foreach ($fields as $alias) {
                $this->attributes[$key][$alias] = $this->aliases[$alias] ?? $alias;
            }
        }
    }

    /**
     * 批量校验
     *
     * @param array $rows
     * @param array $customAttributes
     * @return bool
     */
    public function validate($rows, $customAttributes = []): bool
    {
        foreach ($this->attributes as $key => $fields) {
            list($query, $lines) = $this->buildSql(
                clone $this->query, $rows, $fields
            );

            // where条件对应的行号为空
            // 说明没有生成条件,不做处理
            if (empty($lines)) {
                continue;
            }

            $errMsg = $this->messages[$key] ?? $this->defaultErrorMessage;
            // 通过检查数据库数据判断导入数据是否可用
            $this->errors = $this->validateModels(
                $query->get(), $lines, $fields, $customAttributes, $this->errors, $errMsg
            );
        }

        return empty($this->errors);
    }

    /**
     * 获取错误行
     *
     * @return bool
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * TODO 支持分片查询,防止预处理占位符溢出
     * 生成where条件,并返回where条件对应的行号
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $rows
     * @param array $fields
     */
    protected function buildSql($query, $rows, $fields)
    {
        $lines   = [];
        $aliases = array_keys($fields);
        $count   = count($aliases);
        foreach ($rows as $line => $row) {
            $values = $this->getRowValues($row, $aliases);
            // 没有填写不检查唯一性
            if (count($values) !== $count) {
                continue;
            }

            $query->orWhere(function ($query) use ($fields, $row) {
                // 生成默认筛选条件
                $this->buildDefaultConditions($query);
                // 根据行内指定的字段进行筛选
                $this->buildConditions($query, $fields, $row);
                // 忽视掉满足条件的记录
                $this->buildIgnoreConditions($query, $row);
            });

            $lines[] = [$line, array_to_key($values), $row];
        }

        return [$query->select($fields), $lines];
    }

    /**
     * 获取行内数据
     *
     * @param array $rows
     * @param array $aliases
     */
    protected function getRowValues($row, $aliases)
    {
        $values = [];
        // 按照顺序取出row中的数据
        foreach ($aliases as $alias) {
            if ($row[$alias] === '') {
                continue;
            }

            $values[$alias] = $row[$alias];
        }

        return $values;
    }

    /**
     * 生成默认填充的筛选条件
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $rows
     * @param string $field
     */
    protected function buildDefaultConditions($query)
    {
        foreach ($this->wheres as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                // [ ['id', '>', '123'] ]
                $query->where(...$value);
            } elseif (is_array($value)) {
                // [ ['id' => ['123']] ]
                $query->whereIn($key, $value);
            } else {
                // [ ['id' => '123' ]
                $query->where($key, $value);
            }
        }
    }

    /**
     * 根据行内指定的字段进行筛选
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $rows
     * @param string $field
     */
    protected function buildConditions($query, $fields, $row)
    {
        foreach ($fields as $alias => $field) {
            $query->where($field, $row[$alias]);
        }
    }

    /**
     * 生成需要忽略记录的筛选条件
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $rows
     * @param string $field
     */
    protected function buildIgnoreConditions($query, $row)
    {
        foreach ($this->ignoreFields as $field) {
            $query->where($field, '!=', $row[$field]);
        }
    }

    /**
     * 验证models内容是否错误
     *
     * @param $models
     * @param $lines
     * @param $fields
     * @param $customAttributes
     * @param $errorRows
     * @param $message
     *
     * @return array
     */
    abstract protected function validateModels(
        $models,
        $lines,
        $fields,
        $customAttributes,
        $errorRows,
        $message
    );
}
