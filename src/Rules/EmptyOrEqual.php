<?php

namespace QT\Import\Rules;

use Illuminate\Database\Query\Expression;

/**
 * 检查导入数据与数据库中已存在的数据是否一致或者为空
 * 
 * Class EmptyOrEqual
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
 * 图书分类与系统已存在的图书分类是否一致或者为空(用isbn与name确认唯一书籍)
 * new EmptyOrEqual(
 *     Book::query(), 
 *     [['isbn', 'name']], 
 *     ['department_id' => 123],
 *     ['book_category_code' => 'bookCategory.code'],
 *     ['book_category_code' => '分类号与数据库已有数据不一致']
 * )
 */
class EmptyOrEqual extends Equal
{
    /**
     * 默认错误信息
     * 
     * @var string
     */
    protected $defaultErrorMessage = '与系统已存在数据不一致,请重新填写';

    /**
     *  检查数据是否与数据库的值相等（数据库数据有数据且excel数据不为空时校验）
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
                // 数据库字段为空不对比字段一致
                if (empty(array_get($models->get($key), $field))) {
                    continue;
                }

                // excel对应字段为空
                if (!isset($row[$alias]) || $row[$alias] === '' || $row[$alias] instanceof Expression) {
                    continue;
                }

                if (array_get($models->get($key), $field) == $row[$alias]) {
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
