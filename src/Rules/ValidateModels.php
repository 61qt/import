<?php

namespace QT\Import\Rules;

use Illuminate\Support\Arr;
use QT\Import\Contracts\Validatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as BaseBuilder;

/**
 * ValidateModels
 *
 * @package QT\Import\Rules
 */
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
     * 默认选中字段
     *
     * @var array
     */
    protected $select = [];

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
     * 校验错误时字段对应的展示名称
     *
     * @var array
     */
    protected $customAttributes = [];

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
     * @param Builder|BaseBuilder $query
     * @param array $attributes
     * @param array $wheres
     * @param array $ignoreFields
     * @param array $aliases
     * @param array $messages
     */
    public function __construct(
        Builder | BaseBuilder $query,
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
    public function validate(array $rows, array $customAttributes = []): bool
    {
        $this->customAttributes = $customAttributes;

        foreach ($this->attributes as $key => $fields) {
            list($query, $lines) = $this->buildSql(
                $this->query->clone(),
                $rows,
                $fields
            );

            // where条件对应的行号为空,说明没有生成条件,不做处理
            if (empty($lines)) {
                continue;
            }

            // 通过检查数据库数据判断导入数据是否可用
            $this->validateModels(
                $query->get(),
                $lines,
                $fields,
                $this->formatErrorFields($fields),
                $this->messages[$key] ?? $this->defaultErrorMessage
            );
        }

        return empty($this->errors);
    }

    /**
     * 获取错误行
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * TODO 支持分片查询,防止预处理占位符溢出
     * 生成where条件,并返回where条件对应的行号
     *
     * @param Builder|BaseBuilder $query
     * @param array $rows
     * @param array $fields
     * @return array
     */
    protected function buildSql(Builder | BaseBuilder $query, array $rows, array $fields): array
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

        $select = array_merge($this->select, $fields);

        return [$query->select(array_unique($select)), $lines];
    }

    /**
     * 获取行内数据
     *
     * @param array $row
     * @param array $aliases
     * @return array
     */
    protected function getRowValues(array $row, array $aliases): array
    {
        $values = [];
        // 按照顺序取出row中的数据
        foreach ($aliases as $alias) {
            if ($row[$alias] === '' || $row[$alias] === null) {
                continue;
            }

            $values[$alias] = $row[$alias];
        }

        return $values;
    }

    /**
     * 生成默认填充的筛选条件
     *
     * @param Builder|BaseBuilder $query
     */
    protected function buildDefaultConditions(Builder | BaseBuilder $query)
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
     * @param Builder|BaseBuilder $query
     * @param array $fields
     * @param array $row
     */
    protected function buildConditions(Builder | BaseBuilder $query, array $fields, array $row)
    {
        foreach ($fields as $alias => $field) {
            $query->where($field, $row[$alias]);
        }
    }

    /**
     * 生成需要忽略记录的筛选条件
     *
     * @param Builder|BaseBuilder $query
     * @param array $row
     */
    protected function buildIgnoreConditions(Builder | BaseBuilder $query, array $row)
    {
        foreach ($this->ignoreFields as $field) {
            $query->where($field, '!=', $row[$field]);
        }
    }

    /**
     * 格式化错误字段展示名称
     *
     * @param array $fields
     * @return string
     */
    protected function formatErrorFields(array $fields): string
    {
        $result = '';
        foreach ($fields as $field => $_) {
            $displayName = $this->getFieldDisplayName($field);

            if (!empty($displayName)) {
                $result .= "{$displayName}, ";
            }
        }

        return substr($result, 0, -2);
    }

    /**
     * 获取字段对外展示名称
     *
     * @param string $field
     * @return string
     */
    protected function getFieldDisplayName(string $field): string
    {
        return $this->customAttributes[$field] ?? $field;
    }

    /**
     * 添加错误信息
     *
     * @param int $line
     * @param string $errMsg
     */
    protected function addError(int $line, string $errMsg)
    {
        if (empty($this->errors[$line])) {
            $this->errors[$line] = [];
        }

        $this->errors[$line][] = "原表第{$line}行: {$errMsg}";
    }

    /**
     * 验证models内容是否错误
     *
     * @param Collection $models
     * @param array $lines
     * @param array $fields
     * @param string $errField
     * @param string $errMsg
     */
    abstract protected function validateModels(
        Collection $models,
        array $lines,
        array $fields,
        string $errField,
        string $errMsg
    );
}
