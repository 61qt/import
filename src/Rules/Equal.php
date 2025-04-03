<?php

namespace QT\Import\Rules;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * 检查导入数据与数据库中已存在的数据是否一致
 *
 * Class Equal
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

    /**
     * @param Builder|BaseBuilder $query
     * @param array $attributes
     * @param array $wheres
     * @param array $equalFields
     * @param array $messages
     */
    public function __construct(
        Builder|BaseBuilder $query,
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
            // model不为空时,说明是 Eloquent\Builder
            $query->with($relation);

            $this->select[] = $this->getWithKeyName($model->{$relation}());
        }

        parent::__construct($query, $attributes, $wheres, [], [], $messages);
    }

    /**
     * 获取关联字段
     *
     * @param Relation $relation
     * @throws RuntimeException
     * @return string
     */
    protected function getWithKeyName(Relation $relation): string
    {
        if ($relation instanceof BelongsTo) {
            return $relation->getQualifiedForeignKeyName();
        } elseif ($relation instanceof HasOneOrMany) {
            return $relation->getQualifiedParentKeyName();
        } elseif ($relation instanceof BelongsToMany) {
            return $relation->getQualifiedParentKeyName();
        } elseif ($relation instanceof HasManyThrough) {
            return $relation->getQualifiedLocalKeyName();
        }

        throw new RuntimeException('无法从Relation上获取关联字段');
    }

    /**
     * 检查数据是否与数据库的值相等
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
        $models = $models->keyBy(function ($model) use ($fields) {
            return array_to_key($model->only($fields));
        });

        // excel表导入名 => 在database中的字段名
        foreach ($this->equalFields as [$alias, $field]) {
            $errField = $this->getFieldDisplayName($alias);

            // 验证数据是否与db中一致
            foreach ($lines as [$line, $key, $row]) {
                [$ok, $err] = $this->checkEqual($models, $key, $row, $alias, $field, $errMsg);

                if (!$ok) {
                    $this->addError($line, "{$errField} {$err}");
                }
            }
        }
    }

    /**
     * 检查数据是否相同
     *
     * @param Collection $models
     * @param string $key
     * @param array $row
     * @param string $alias 在row中的字段名
     * @param string $field 在model中的字段名
     * @param string $errMsg 错误信息
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

        $data = $models->get($key);
        // 防止Arr::get()时自动加载关联数据
        // 提前将可以访问的内容取出来合并为数组
        if ($data instanceof Model) {
            $data = array_merge($data->getAttributes(), $data->getRelations());
        }

        return Arr::get($data, $field) == $row[$alias]
            ? [true, null]
            : [false, $errMsg];
    }
}
