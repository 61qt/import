<?php

namespace QT\Import\Traits;

use RuntimeException;
use QT\Import\Exceptions\Error;
use Illuminate\Support\Facades\DB;
use QT\Import\Contracts\Dictionary;
use Illuminate\Support\Facades\Validator;
use Box\Spout\Writer\Common\Helper\CellHelper;

trait CheckAndFormat
{
    /**
     * @var array 字段校验规则 (需要配置)
     */
    protected $rules = [];

    /**
     * @var array 字段默认值
     */
    protected $default = [];

    /**
     * @var bool
     */
    protected $useDefault = true;

    /**
     * @var array validate 自定义错误信息
     */
    protected $messages = [];

    /**
     * @var array validate 自定义属性
     */
    protected $customAttributes = [];

    /**
     * @var QT\Import\Contracts\Dictionary
     */
    protected $dictionary;

    /**
     * @var array 字典错误时自定义错误信息
     */
    protected $dictErrorMessages = [];

    /**
     * @param array $data
     * @param int $line
     * @param array $fields
     * @return array
     */
    protected function checkRow($data, $line, $fields)
    {
        // 验证参数格式
        $validator = Validator::make(
            $data, $this->rules, $this->messages, $this->getCustomAttributes()
        );

        if ($validator->fails()) {
            $errMsg = [];
            foreach ($validator->errors()->messages() as $field => $msgs) {
                $pos      = $this->getSheetPos(array_search($field, $fields), $line);
                $errMsg[] = sprintf('原表%s: %s', $pos, join(',', $msgs));
            }

            throw new Error(join("\n", $errMsg));
        }

        return [$data, $line, $fields];
    }

    /**
     * 获取字段展示内容
     *
     * @return array
     */
    protected function getCustomAttributes()
    {
        return array_merge($this->fields, $this->customAttributes);
    }

    /**
     * @param array $data
     * @param int $line
     * @param array $fields
     * @return array
     */
    protected function formatRow($data, $line, $fields)
    {
        $errors = [];

        foreach ($data as $field => $value) {
            try {
                // 转换枚举类型
                $data[$field] = $this->fromDict($field, $value);
            } catch (RuntimeException $e) {
                // 记录字典不匹配的错误
                $pos      = $this->getSheetPos(array_search($field, $fields), $line);
                $errors[] = "原表{$pos} 错误: {$e->getMessage()}";
            }

            // 过滤空值
            if ($data[$field] !== '') {
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

        if (!empty($errors)) {
            throw new Error(join(',', $errors));
        }

        return [$data, $line, $fields];
    }

    /**
     * 获取字段默认值
     *
     * @param string $field
     * @return mixed|\Illuminate\Database\Query\Expression
     */
    protected function getDefaultValue($field)
    {
        return array_key_exists($field, $this->default)
            ? $this->default[$field]
            : DB::raw('default');
    }

    /**
     * 如果字段有对应的字典，从字典中进行转换
     *
     * @param string $field
     * @param string $value
     * @param array $dictionaries
     * @param mixed
     * @throws RuntimeException
     */
    protected function fromDict($field, $value)
    {
        $field        = $this->extraDictFieldMaps[$field] ?? $field;
        $dictionaries = $this->dictionary->getDictionaries();

        if (!array_key_exists($field, $dictionaries)) {
            return $value;
        }

        $optionalDict = $this->dictionary->getOptionalDictionaries($dictionaries);
        $keyName      = isset($optionalDict[$field]) ? 'name' : 'code';
        $dict         = collect($dictionaries[$field])->keyBy($keyName);

        if ($dict->offsetExists($value)) {
            return $dict->get($value)['value'];
        }

        // 如果填写了枚举类型，且不在枚举范围内
        if ($value !== '' && !is_null($value)) {
            // 优先使用自定义的字段名,其次才是导入模板中的字段名
            if (isset($this->customAttributes[$field])) {
                $column = $this->customAttributes[$field];
            } elseif (isset($this->fields[$field])) {
                $column = $this->fields[$field];
            } else {
                $column = $field;
            }

            $message = empty($this->dictErrorMessages[$field])
                ? "\"{$column}\"必须为 :" . join(', ', $dict->pluck($keyName)->toArray())
                : $this->dictErrorMessages[$field];

            throw new RuntimeException($message);
        }

        return $value;
    }

    /**
     * 获取excel中的坐标
     *
     * @param $column
     * @param $line
     * @return string
     */
    protected function getSheetPos($column, $line)
    {
        return CellHelper::getColumnLettersFromColumnIndex($column) . $line;
    }
}
