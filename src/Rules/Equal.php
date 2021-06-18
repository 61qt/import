<?php

namespace QT\Import\Rules;

use Illuminate\Support\Arr;

/**
 * 检查导入数据与数据库中已存在的数据是否一致
 *
 * Class Equal
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
 * equalFields:
 *     excel表与db数据进行相等校验的字段,支持通过"."的方式加载relation字段
 * messages:
 *     字段校验错误时自定义的错误信息
 *
 * eq:
 * 图书分类与系统已存在的图书分类是否一致(用isbn与name确认唯一书籍)
 * new Equal(
 *     Book::query(),
 *     [['isbn', 'name']],
 *     ['department_id' => 123],
 *     ['book_category_code' => 'bookCategory.code'],
 *     ['book_category_code' => '分类号与数据库已有数据不一致']
 * )
 */
class Equal extends ValidateModels
{
    /**
     * 默认错误信息
     *
     * @var string
     */
    protected $defaultErrorMessage = '与系统已存在数据不一致,请重新填写';

    /**
     * 相等的字段
     *
     * @var array
     */
    protected $equalFields = [];

    public function __construct(
        $query,
        array $attributes,
        array $wheres = [],
        array $equalFields = [],
        array $messages = []
    ) {
        $this->equalFields = $equalFields;

        parent::__construct($query, $attributes, $wheres, [], [], $messages);
    }

    protected function buildSql($query, $rows, $fields)
    {
        list($query, $lines) = parent::buildSql($query, $rows, $fields);

        $model = $query->getModel();

        if (property_exists($model, 'withFields')) {
            $fields = array_merge($fields, $model->withFields);
        }
        foreach ($this->equalFields as $field) {
            if (false === strpos($field, '.')) {
                $fields[] = $field;
            }
        }

        // 默认选中全部关联字段,保证不影响with
        return [$query->select($fields), $lines];
    }

    /**
     * 检查数据是否与数据库的值相等
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
        $models = $models->keyBy(function ($model) use ($fields) {
            return array_to_key($model->only($fields));
        });

        // excel表导入名 => 在database中的字段名
        foreach ($this->equalFields as $alias => $field) {
            if (is_int($alias)) {
                $alias = $field;
            }

            if (false !== strpos($field, '.')) {
                list($relation) = explode('.', $field, 2);
                // 关联
                $models->load($relation);
            }

            // 验证数据是否与db中一致
            foreach ($lines as [$line, $key, $row]) {
                // 如果数据库中没有不做检查
                if (!$models->has($key)) {
                    continue;
                }

                if (Arr::get($models->get($key), $field) == $row[$alias]) {
                    continue;
                }

                if (empty($errorRows[$line])) {
                    $errorRows[$line] = [];
                }

                $errorFields = $customAttributes[$alias] ?? $alias;

                $errorRows[$line][] = sprintf(
                    '原表第%s行: %s %s', $line, $errorFields, $message
                );
            }
        }

        return $errorRows;
    }
}
