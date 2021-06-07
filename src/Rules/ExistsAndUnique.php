<?php

namespace QT\Import\Rules;

/**
 * 检查导入数据再数据库中是否存在,如果不存在就抛出错误
 * 
 * Class ExistsAndUnique
 * @package QT\Import\Rules
 * 
 * 参数说明:
 * 
 * query:
 *     laravel sql builder,可以提前设置部分条件再传入
 * attributes:
 *     进行筛选的字段,会把每行指定的字段作为where条件填入sql
 * allowNullFields:
 *     根据筛选结果进行对比的字段
 * wheres:
 *     默认的筛选条件,每一行单独注入,与同一行的筛选条件为and的关系
 * ignoreFields:
 *     忽略掉相同的字段
 * aliases:
 *     excel字段再db中的真实字段名
 * notFoundMessage:
 *     数据不存在时抛出的错误信息
 * notUniqueMessage:
 *     数据重复时抛出的错误信息
 * 
 * eq: 
 * 根据名称检查用户是否存在,如果存在多条,再用身份证进行检查是否具体身份证下的用户是否存在
 * new ExistsAndUnique(
 *     User::query(), 
 *     ['name'], 
 *     ['id_number'],
 *     ['department_id' => 123],
 *     ['id'],
 *     ['excel内字段名' => '数据库中字段名'],
 *     ['id_number' => '身份证不存在', 'email' => '邮箱不存在']
 * )
 */
class ExistsAndUnique extends ValidateModels
{
    protected $allowNullFields = [];

    protected $notFoundMessage = '不存在的数据';

    protected $notUniqueMessage = '相同的数据';

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $attributes
     * @param array $allowNullFields
     * @param array $wheres
     * @param array $ignoreFields
     * @param array $aliases
     * @param string $notFoundMessage
     * @param string $notUniqueMessage
     */
    public function __construct(
        $query,
        array $attributes,
        array $allowNullFields = [],
        array $wheres = [],
        array $ignoreFields = [],
        array $aliases = [],
        string $notFoundMessage = null,
        string $notUniqueMessage = null
    ) {
        parent::__construct($query, $attributes, $wheres, $ignoreFields, $aliases);

        $this->notFoundMessage  = $notFoundMessage ?: $this->notFoundMessage;
        $this->notUniqueMessage = $notUniqueMessage ?: $this->notUniqueMessage;

        foreach ($allowNullFields as $field) {
            $this->allowNullFields[$field] = $this->aliases[$field] ?? $field;
        }
    }

    /**
     * 生成where条件,并返回where条件对应的行号
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $rows
     * @param array $fields
     */
    protected function buildSql($query, $rows, $fields)
    {
        [$query, $lines] = parent::buildSql($query, $rows, $fields);

        return [$query->addSelect($this->allowNullFields), $lines];
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

        foreach ($this->allowNullFields as $alias => $field) {
            if (!empty($row[$alias])) {
                $query->where($field, $row[$alias]);
            }
        }
    }

    /**
     * 检查数据是否不存在
     *
     * @param $models
     * @param $lines
     * @param $fields
     * @param $errorRows
     * @param $message
     * 
     * @return array
     */
    protected function validateModels(
        $models,
        $lines,
        $fields,
        $customAttributes,
        $errorRows,
        $message
    ) {
        $groups = $models->groupBy(function ($model) use ($fields) {
            return $model->toKey($fields);
        });

        // 获取数据不存在的错误行
        foreach ($lines as [$line, $key, $row]) {
            [$ok, $error] = $this->checkGroup($groups, $key, $row);

            if ($ok) {
                continue;
            }

            if (empty($errorRows[$line])) {
                $errorRows[$line] = [];
            }

            $errorRows[$line][] = "原表第{$line}行: {$error}";
        }

        return $errorRows;
    }

    /**
     * 检查数据是否存在且不重复
     *
     * @param $groups
     * @param $key
     * @param $row
     * 
     * @return array
     */
    protected function checkGroup($groups, $key, $row)
    {
        $values = $this->getRowValues($row, array_keys($this->allowNullFields));

        // 关键词对不上返回不存在
        if (!$groups->has($key)) {
            return [false, $this->notFoundMessage];
        }

        $group = $groups->get($key);
        if ($group->count() === 1) {
            return [true, $group->first()];
        }

        // 关键词对上,但是有多条记录,并且没有其他附加属性帮忙判断,返回有重复记录
        if (empty(array_filter($values))) {
            return [false, $this->notUniqueMessage];
        }

        $results = $group->filter(function ($models) use ($values) {
            return empty(array_diff($values, $models->only($this->allowNullFields)));
        });

        // 关键词对上,但是其余属性不同的数据,返回不存在
        if ($results->isEmpty()) {
            return [false, $this->notFoundMessage];
        }

        // 多条数据匹配成功,返回有重复记录
        if ($results->count() > 1) {
            return [false, $this->notUniqueMessage];
        }

        return [true, $results->first()];
    }
}
