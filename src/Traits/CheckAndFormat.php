<?php

namespace QT\Import\Traits;

use DateTime;
use Illuminate\Container\Container;
use QT\Import\Contracts\Dictionary;
use Illuminate\Database\Query\Expression;
use Illuminate\Contracts\Validation\Factory;
use QT\Import\Exceptions\ValidationException;
use Illuminate\Validation\ValidationRuleParser;

/**
 * 给每一行进行校验并格式化为期望的数据
 *
 * @package QT\Import\Traits
 */
trait CheckAndFormat
{
    /**
     * 校验器生成工厂
     *
     * @var Factory
     */
    protected $factory;

    /**
     * 字段校验规则 (需要配置)
     *
     * @var array
     */
    protected $rules = [];

    /**
     * validate 自定义错误信息
     *
     * @var array
     */
    protected $messages = [];

    /**
     * validate 错误字段展示名称
     *
     * @var array
     */
    protected $displayNames = [];

    /**
     * 需要格式化日期的字段
     * 如 'birthday' => 'Ymd'
     *
     * @var array
     */
    protected $fieldDateFormats = [];

    /**
     * 是否使用默认值填充
     *
     * @var bool
     */
    protected $useDefault = true;

    /**
     * 字段默认值
     *
     * @var array
     */
    protected $default = [];

    /**
     * 可用字典
     *
     * @var array<string, Dictionary>
     */
    protected $dictionaries = [];

    /**
     * 字典错误时自定义错误信息
     *
     * @var array
     */
    protected $dictErrorMessages = [];

    /**
     * 导入可选列
     *
     * @var array
     */
    protected $optional = [];

    /**
     * 初始化错误信息,不用每行都判断一次错误名
     * 
     * @return void
     */
    protected function bootDictErrorMessages()
    {
        foreach ($this->dictionaries as $field => $dict) {
            if (!empty($this->dictErrorMessages[$field])) {
                continue;
            }

            $this->dictErrorMessages[$field] = sprintf(
                "%s必须为: %s", 
                $this->displayNames[$field] ?? $field, 
                join(', ', $dict->keys()),
            );
        }
    }

    /**
     * 加载需要格式化日期的字段规则
     *
     * @return void
     */
    protected function bootFieldDateFormats()
    {
        foreach ($this->rules as $field => $rules) {
            if (empty($rules)) {
                $rules = [];
            }

            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $rules = array_reduce($rules, function ($result, $rule) {
                [$rule, $params] = ValidationRuleParser::parse($rule);

                $result[$rule] = $params;

                return $result;
            }, []);

            if (isset($rules['DateFormat']) && !isset($this->fieldDateFormats[$field])) {
                $this->fieldDateFormats[$field] = $rules['DateFormat'][0];
            }
        }
    }

    /**
     * 获取所有字典
     *
     * @return array<Dictionary>
     */
    public function getDictionaries(): array
    {
        return $this->dictionaries;
    }

    /**
     * 获取导入字段对应的字典
     *
     * @param string $field
     * @return Dictionary|null
     */
    public function getDictionary(string $field): ?Dictionary
    {
        if (empty($this->dictionaries[$field])) {
            return null;
        }

        return $this->dictionaries[$field];
    }

    /**
     * 给导入字段指定字典
     *
     * @param string $field
     * @param Dictionary $dict
     */
    public function setDictionary(string $field, Dictionary $dict)
    {
        $this->dictionaries[$field] = $dict;
    }

    /**
     * 格式化初始数据
     * 
     * @param array $row
     * @return array
     */
    protected function formatRow(array $row): array
    {
        // 提前格式化datetime类型
        foreach ($this->fieldDateFormats as $field => $format) {
            if ($row[$field] instanceof DateTime) {
                $row[$field] = $row[$field]->format($format);
            }
        }

        $errors = [];
        foreach ($this->optional as $field) {
            if (!isset($row[$field]) || empty($this->dictionaries[$field])) {
                continue;
            }

            $value = $this->formatDict($row[$field], $this->dictionaries[$field]);

            if ($value !== false) {
                $row[$field] = $value;
            } else {
                $errors[$field] = $this->dictErrorMessages[$field];
            }
        }

        $this->throwNotEmpty($errors);

        return $row;
    }

    /**
     * 把excel填写的内容转换成需要的内容
     *
     * @param string $key
     * @param Dictionary $dict
     * @return mixed
     */
    protected function formatDict(string $key, Dictionary $dict): mixed
    {
        if ($dict->has($key)) {
            return $dict->get($key);
        }

        // 没有填写的字典不做检查,如果有必填需求,由后续的rules定义
        return $key === '' ? $key : false;
    }

    /**
     * 校验数据是否正确
     * 
     * @param array $row
     * @throws ValidationException
     * @return array
     */
    protected function checkRow(array $row): array
    {
        // 验证参数格式
        $validator = $this->getValidationFactory()->make(
            $row,
            $this->rules,
            $this->messages,
            $this->displayNames,
        );

        $this->throwNotEmpty($validator->errors()->messages());

        return $row;
    }

    /**
     * 获取字段展示内容
     *
     * @param array $errors
     * @throws ValidationException
     */
    protected function throwNotEmpty(array $errors)
    {
        if (empty($errors)) {
            return;
        }

        $messages = [];
        foreach ($errors as $error) {
            if (is_array($error)) {
                $error = join(',', $error);
            }

            $messages[] = $error;
        }

        throw new ValidationException(join("\n", $messages));
    }

    /**
     * 获取字段默认值
     *
     * @param string $field
     * @return mixed|\Illuminate\Database\Query\Expression
     */
    protected function getDefaultValue(string $field)
    {
        return array_key_exists($field, $this->default)
            ? $this->default[$field]
            : new Expression('default');
    }

    /**
     * @return Factory
     */
    protected function getValidationFactory(): Factory
    {
        return $this->factory ?: Container::getInstance()->get(Factory::class);
    }

    /**
     * @param Factory $factory
     */
    public function setValidationFactory(Factory $factory)
    {
        $this->factory = $factory;
    }
}
