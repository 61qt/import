<?php

namespace QT\Import\Rules;

/**
 * 检查导入数据再db中是否唯一,如果不唯一抛出错误
 *
 * Class UniqueIgnoreFieldNull
 * @package QT\Import\Rules
 *
 * 参数说明:
 *
 * query:
 *     laravel sql builder,可以提前设置部分条件再传入
 * attributes:
 *     进行筛选的字段,会把每行指定的字段作为where条件填入sql
 * wheres:
 *     默认的筛选条件,每一行单独注入,与同一行的筛选条件为and的关系
 * ignoreFieldsNull:
 *     忽略掉相同的字段
 * aliases:
 *     excel字段再db中的真实字段名
 * messages:
 *     字段校验错误时自定义的错误信息
 *
 * eq:
 * 检查当前id以外是否有用户已经占用了身份证
 * new UniqueIgnoreFieldNull(
 *     User::query(),
 *     [['id_number', 'user_type'], 'email'],
 *     ['department_id' => 123],
 *     ['id'],
 *     [],
 *     ['id_number' => '身份证已存在', 'email' => '邮箱已存在']
 * )
 */
class UniqueIgnoreFieldNull extends Unique
{
    /**
     * 配置忽略满足条件是否有 null 数据
     *
     * @var array
     */
    protected $ignoreFieldsNull = [];

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
     * @param array $ignoreFieldsNull ['id_number' => true, 'phone' => false]
     * @param array $aliases
     * @param array $messages
     */
    public function __construct(
        $query,
        array $attributes,
        array $wheres = [],
        array $ignoreFieldsNull = [],
        array $aliases = [],
        array $messages = []
    ) {
        parent::__construct(
            $query,
            $attributes,
            $wheres,
            [],
            $aliases,
            $messages
        );

        $this->ignoreFieldsNull = $ignoreFieldsNull;
    }

    /**
     * 生成需要忽略记录的筛选条件
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @param array $rows
     */
    protected function buildIgnoreConditions($baseQuery, $row)
    {
        foreach ($this->ignoreFieldsNull as $field => $bool) {
            $baseQuery->where(function ($query) use ($row, $field, $bool) {
                $query->where($field, '!=', $row[$field]);

                if ($bool) {
                    $query->orWhereNull($field);
                }
            });
        }
    }
}
