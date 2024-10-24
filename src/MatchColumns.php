<?php

namespace QT\Import;

use Iterator;
use QT\Import\Exceptions\FieldException;

/**
 * MatchColumns
 *
 * @package QT\Import
 */
class MatchColumns
{
    public const STRICT_MODE   = 1;
    public const TOLERANT_MODE = 2;

    /**
     * 校验模式
     *
     * @var bool
     */
    protected $mode = self::TOLERANT_MODE;

    /**
     * 默认跳过的行数
     *
     * @var int
     */
    protected $startRow = 0;

    /**
     * 字段错误提示语
     *
     * @var string
     */
    protected $fieldErrorMsg = '导入模板与系统提供的模板不一致，请重新导入';

    /**
     * @param array $fields
     * @param array $options
     */
    public function __construct(protected array $fields, protected array $options = [])
    {
        if (isset($this->options['start_row'])) {
            $this->startRow = $this->options['start_row'];
        }

        if (isset($this->options['mode'])) {
            $this->mode = $this->options['mode'];
        }
    }

    /**
     * 根据首行数据匹配列名
     *
     * @param Iterator $rows
     * @throws FieldException
     * @return array
     */
    public function __invoke(Iterator $rows): array
    {
        $results = [];
        $count   = count($this->fields);
        $columns = array_flip($this->fields);

        for ($i = 0; $i < $this->startRow; $i++) {
            $rows->next();
        }

        foreach ($rows->current() as $index => $value) {
            // 超过最大列数后不再取值
            if ($index === $count) {
                break;
            }

            $pos = strpos($value, '(');
            // 剔除括号内的内容
            if (false !== $pos) {
                $value = substr($value, 0, $pos);
            }

            if (empty($columns[$value])) {
                throw new FieldException($this->fieldErrorMsg);
            }

            $results[$index] = $columns[$value];
        }

        // 严格模式下字段必须完全匹配
        if ($this->mode === static::STRICT_MODE && count($columns) !== count($results)) {
            throw new FieldException($this->fieldErrorMsg);
        }

        // 跳过表头
        $rows->next();

        return $results;
    }
}
