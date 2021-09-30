<?php

namespace QT\Import\Rules;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
 * new Equal(
 *     Model::query(),
 *     ['id'],
 *     ['foo' => 'bar'],
 *     ['name' => 'name', 'relation_data' => 'relation.data'],
 *     ['name' => '名称不一致', 'relation_data' => '与关联数据对比不一致']
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
        $model = $query instanceof Builder 
            ? $query->getModel() 
            : null;

        // 检查有没有关联字段
        foreach ($equalFields as $alias => $field) {
            if (is_int($alias)) {
                $alias = $field;
            }

            $this->equalFields[] = [$alias, $field];

            if (false === strpos($field, '.')) {
                $this->select[] = $field;
                continue;
            }

            $relation = Arr::first(explode('.', $field));

            if ($model === null || !method_exists($model, $relation)) {
                continue;
            }

            $this->select[] = $this->getWithKeyName($model->{$relation}());
        }
    
        parent::__construct($query, $attributes, $wheres, [], [], $messages);
    }

    /**
     * 获取关联字段
     *
     * @param Relation $relation
     * @return string
     * @throws RuntimeException
     */
    protected function getWithKeyName(Relation $relation): string
    {
        if ($relation instanceof BelongsTo) {
            return $relation->getForeignKeyName();
        } elseif ($relation instanceof HasOneOrMany) {
            return $relation->getLocalKeyName();
        } elseif ($relation instanceof BelongsToMany) {
            return $relation->getParentKeyName();
        } elseif ($relation instanceof HasManyThrough) {
            return $relation->getLocalKeyName();
        }

        throw new RuntimeException("无法从Relation上获取关联字段");
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
        foreach ($this->equalFields as [$alias, $field]) {
            if (false !== strpos($field, '.')) {
                $models->load(Arr::first(explode('.', $field, 2)));
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
