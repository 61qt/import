<?php

namespace QT\Import\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as BaseBuilder;

/**
 * 根据单行中特定的字段检查是否存在
 * 如果存在并且有多条,再根据另外的字段进行第二次匹配
 *
 * Class ExistsAndUnique
 *
 * @package App\Tasks\Import\Rules
 *
 * 参数说明:
 *
 * query:
 *     laravel sql builder,可以提前设置部分条件再传入
 * attributes:
 *     进行筛选的字段,会把每行指定的字段作为where条件填入sql
 * nullable:
 *     允许为空的字段
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
 * 根据名称检查用户是否存在,如果有多条,再根据身份证检查进行精准匹配
 * new ExistsAndUnique(
 *     User::query(),
 *     ['name'],
 *     ['id_number'],
 *     ['department_id' => 123],
 *     ['id'],
 *     ['excel内字段名' => '数据库中字段名'],
 *     '数据不存在时抛出的错误信息',
 *     '数据重复时抛出的错误信息'
 * )
 */
class ExistsAndUnique extends ValidateModels
{
    /**
     * 允许为空的字段
     *
     * @var array
     */
    protected $nullable = [];

    /**
     * 导入时的列名
     *
     * @var array
     */
    protected $columns = [];

    /**
     * 不存在时的错误
     *
     * @var string
     */
    protected $notFoundMessage = '不存在的数据';

    /**
     * 重复时的错误
     *
     * @var string
     */
    protected $notUniqueMessage = '相同的数据';

    /**
     * @param Builder|BaseBuilder $query
     * @param array $attributes
     * @param array $nullable
     * @param array $wheres
     * @param array $ignoreFields
     * @param array $aliases
     * @param string $notFoundMessage
     * @param string $notUniqueMessage
     */
    public function __construct(
        Builder|BaseBuilder $query,
        array $attributes,
        array $nullable = [],
        array $wheres = [],
        array $ignoreFields = [],
        array $aliases = [],
        ?string $notFoundMessage = null,
        ?string $notUniqueMessage = null
    ) {
        parent::__construct($query, $attributes, $wheres, $ignoreFields, $aliases);

        foreach ($nullable as $field) {
            $column = $this->aliases[$field] ?? $field;

            $this->select[]         = $column;
            $this->columns[]        = $field;
            $this->nullable[$field] = $column;
        }

        $this->notFoundMessage  = $notFoundMessage ?: $this->notFoundMessage;
        $this->notUniqueMessage = $notUniqueMessage ?: $this->notUniqueMessage;
    }

    /**
     * 根据行内指定的字段进行筛选
     *
     * @param Builder|BaseBuilder $query
     * @param array $fields
     * @param array $row
     * @return void
     */
    protected function buildConditions(Builder|BaseBuilder $query, array $fields, array $row)
    {
        foreach ($fields as $alias => $field) {
            $query->where($field, $row[$alias]);
        }

        foreach ($this->nullable as $alias => $field) {
            if (!empty($row[$alias])) {
                $query->where($field, $row[$alias]);
            }
        }
    }

    /**
     * 检查数据是否不存在
     *
     * @param Collection $models
     * @param array $lines
     * @param array $fields
     * @param string $errField
     * @param string $errMsg
     */
    protected function validateModels(
        Collection $models,
        array $lines,
        array $fields,
        string $errField,
        string $errMsg
    ) {
        $groups = $models->groupBy(function ($model) use ($fields) {
            return array_to_key($model->only($fields));
        });

        // 获取数据不存在的错误行
        foreach ($lines as [$line, $key, $row]) {
            [$ok, $err] = $this->checkGroup($groups, $key, $row);

            if (!$ok) {
                $this->addError($line, "{$errField} {$err}");
            }
        }
    }

    /**
     * 检查数据是否存在且不重复
     *
     * @param Collection $groups
     * @param string $key
     * @param array $row
     * @return array
     */
    protected function checkGroup(Collection $groups, string $key, array $row): array
    {
        $values = $this->getRowValues($row, $this->columns);

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
            return empty(array_diff($values, $models->only($this->select)));
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
