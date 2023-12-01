<?php

namespace QT\Import\Rules;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Collection;

/**
 * 检查导入数据与数据库中已存在的数据是否一致或者为空
 *
 * Class EmptyOrEqual
 *
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
     * 检查数据是否与数据库的值相等(数据库数据有数据且excel数据不为空时校验)
     *
     * @param Collection $models
     * @param string $key
     * @param array $row
     * @param string $alias 在row中的字段名
     * @param string $field 在model中的字段名
     * @param string $errMsg
     * @return array
     */
    protected function checkEqual(
        Collection $models,
        string $key,
        array $row,
        string $alias,
        string $field,
        string $errMsg
    ): array {
        // 如果数据库中没有不做检查
        if (!$models->has($key)) {
            return [true, null];
        }

        $value = Arr::get($models->get($key), $field);
        // 数据库字段为空不对比字段一致
        if ($value === null || $value === '') {
            return [true, null];
        }

        // excel对应字段为空
        if (!isset($row[$alias]) || $row[$alias] === '' || $row[$alias] instanceof Expression) {
            return [true, null];
        }

        return $value == $row[$alias] ? [true, null] : [false, $errMsg];
    }
}
