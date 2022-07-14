<?php

namespace QT\Import\Traits;

use RuntimeException;
use Illuminate\Container\Container;
use QT\Import\Contracts\Dictionary;
use Illuminate\Database\Query\Expression;
use Illuminate\Contracts\Validation\Factory;
use QT\Import\Exceptions\ValidationException;
use Box\Spout\Writer\Common\Helper\CellHelper;

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
     * validate 自定义属性
     *
     * @var array
     */
    protected $customAttributes = [];

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
     * 初始化错误信息,不用每行都判断一次错误名
     */
    protected function bootDictErrorMessages()
    {
        $fields = $this->getFields($this->input);

        foreach ($this->dictionaries as $field => $dict) {
            if (!empty($this->dictErrorMessages[$field])) {
                continue;
            }

            $keys = join(', ', $dict->keys());
            // 优先使用自定义的字段名,其次才是导入模板中的字段名
            if (isset($this->customAttributes[$field])) {
                $column = $this->customAttributes[$field];
            } elseif (isset($fields[$field])) {
                $column = $fields[$field];
            } else {
                $column = $field;
            }

            $this->dictErrorMessages[$field] = "\"{$column}\"必须为: {$keys}";
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
        if ($key === '' || $key === null) {
            return $key;
        }

        return false;
    }

    /**
     * @param array $data
     * @param int $line
     * @return array
     */
    protected function checkRow(array $data, int $line): array
    {
        // 验证参数格式
        $validator = $this->getValidationFactory()->make(
            $data,
            $this->rules,
            $this->messages,
            $this->getCustomAttributes()
        );

        $this->throwNotEmpty($validator->errors()->messages(), $line);

        return [$data, $line];
    }

    /**
     * @param array $data
     * @param int $line
     * @return array
     */
    protected function formatRow(array $data, int $line): array
    {
        foreach ($data as $field => $value) {
            // 过滤空值
            if ($value !== '') {
                continue;
            }

            if ($this->useDefault) {
                // 批量插入时需要设置默认值
                $data[$field] = $this->getDefaultValue($field);
            } else {
                // 更新信息时保留原记录
                unset($data[$field]);
            }
        }

        return [$data, $line];
    }

    /**
     * 获取字段展示内容
     *
     * @param array $errors
     * @param int $line
     * @throws RuntimeException
     */
    protected function throwNotEmpty(array $errors, int $line)
    {
        if (empty($errors)) {
            return;
        }

        $messages = [];
        $fields   = array_keys($this->getFields($this->input));
        foreach ($errors as $field => $message) {
            if (is_array($message)) {
                $message = join(',', $message);
            }

            $index = array_search($field, $fields);
            $pos   = $this->getSheetPos($index, $line);

            $messages[] = "原表{$pos} 错误: {$message}";
        }

        throw new ValidationException(join("\n", $messages));
    }

    /**
     * 获取字段展示内容
     *
     * @return array
     */
    protected function getCustomAttributes(): array
    {
        return array_merge($this->getFields($this->input), $this->customAttributes);
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
     * 获取excel中的坐标
     *
     * @param int $columnIndex
     * @param int $line
     * @return string
     */
    protected function getSheetPos(int $columnIndex, int $line): string
    {
        return CellHelper::getColumnLettersFromColumnIndex($columnIndex) . $line;
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
