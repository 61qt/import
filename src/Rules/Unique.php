<?php

namespace QT\Import\Rules;

use Illuminate\Database\Eloquent\Collection;

/**
 * 检查导入数据再db中是否唯一,如果不唯一抛出错误
 * 
 * Class Unique
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
 * 检查当前id以外是否有用户已经占用了身份证
 * new Unique(
 *     User::query(), 
 *     [['id_number', 'user_type'], 'email'], 
 *     ['department_id' => 123],
 *     ['id'],
 *     ['excel内字段名' => '数据库中字段名'],
 *     ['id_number' => '身份证已存在', 'email' => '邮箱已存在']
 * )
 */
class Unique extends ValidateModels
{
    /**
     * 默认错误信息
     * 
     * @var string
     */
    protected $defaultErrorMessage = '已存在,请保证数据不重复后再次导入';

    /**
     * 检查数据是否重复
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
        if ($models->isEmpty()) {
            return;
        }

        $models = $models->keyBy(function ($model) use ($fields) {
            return array_to_key($model->only($fields));
        });

        // 获取所有重复的记录坐标
        foreach ($lines as [$line, $key, $row]) {
            [$ok, $errMsg] = $this->checkExists($models, $key, $row, $errMsg);

            if (!$ok) {
                $this->addError($line, "{$errField} {$errMsg}");
            }
        }
    }

    /**
     * 检查数据是否重复
     *
     * @param $models
     * @param $key
     * @param $row
     * @param $errMsg
     * 
     * @return array
     */
    protected function checkExists($models, $key, $row, $errMsg)
    {
        return $models->has($key)
            ? [false, $errMsg]
            : [true, null];
    }
}
