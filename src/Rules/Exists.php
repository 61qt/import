<?php

namespace QT\Import\Rules;

/**
 * 检查导入数据再数据库中是否存在,如果不存在就抛出错误
 * 
 * Class Exists
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
 * ignoreFields:
 *     忽略掉相同的字段
 * aliases:
 *     excel字段再db中的真实字段名
 * messages:
 *     字段校验错误时自定义的错误信息
 * 
 * eq: 
 * 检查除了id相同的以外用户身份证是否存在
 * new Exists(
 *     User::query(), 
 *     [['id_number', 'user_type'], 'email'], 
 *     ['department_id' => 123],
 *     ['id'],
 *     ['excel内字段名' => '数据库中字段名'],
 *     ['id_number' => '身份证不存在', 'email' => '邮箱不存在']
 * )
 */
class Exists extends ValidateModels
{
    /**
     * 默认错误信息
     * 
     * @var string
     */
    protected $defaultErrorMessage = '不存在,保证数据已存在时再尝试导入';

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
        $models = $models->keyBy(function ($model) use ($fields) {
            return array_to_key($model->only($fields));
        });

        $errorFields = join(', ', array_map(function ($alias) use ($customAttributes) {
            return $customAttributes[$alias] ?? $alias;
        }, array_keys($fields)));

        // 获取数据不存在的错误行
        foreach ($lines as [$line, $key, $row]) {
            [$ok, $message] = $this->checkExists($models, $key, $row, $message);

            if ($ok) {
                continue;
            }

            if (empty($errorRows[$line])) {
                $errorRows[$line] = [];
            }

            $errorRows[$line][] = "原表第{$line}行: {$errorFields} {$message}";
        }

        return $errorRows;
    }

    /**
     * 检查数据是否存在
     *
     * @param $models
     * @param $key
     * @param $row
     * @param $message
     * 
     * @return array
     */
    protected function checkExists($models, $key, $row, $message)
    {
        return $models->has($key)
            ? [true, null]
            : [false, $message];
    }
}
