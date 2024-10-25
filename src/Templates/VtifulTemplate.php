<?php

namespace QT\Import\Templates;

use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;
use Vtiful\Kernel\Validation;
use QT\Import\Contracts\Dictionary;
use Illuminate\Database\Query\Builder;
use QT\Import\Exceptions\TemplateException;
use Illuminate\Validation\ValidationRuleParser;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use QT\Import\Contracts\Template as ContractTemplate;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Excel导入模板
 *
 * @package QT\Import\Templates
 */
class VtifulTemplate implements ContractTemplate
{
    /**
     * 导入sheet index
     *
     * @var string
     */
    protected $importSheetIndex;

    /**
     * 导入sheet标题
     *
     * @var string
     */
    protected $importSheetTitle;

    /**
     * 字典sheet标题
     *
     * @var string
     */
    protected $dictSheetTitle;

    /**
     * 列名
     *
     * @var array
     */
    protected $columns = [];

    /**
     * 每一列的校验规则
     *
     * @var array
     */
    protected $rules = [];

    /**
     * 列备注信息
     *
     * @var array
     */
    protected $remarks = [];

    /**
     * 列对应的字典
     *
     * @var array
     */
    protected $dictionaries = [];

    /**
     * 允许下拉的字段
     *
     * @var array
     */
    protected $optionalColumns = [];

    /**
     * 设置需要格式的列
     *
     * @var array
     */
    protected $formatColumns = [];

    /**
     * 导入示例内容
     *
     * @var array
     */
    protected $example = [];

    /**
     * 校验提示语
     *
     * @var array
     */
    protected $ruleComments = [];

    /**
     * 校验规则列样式
     *
     * @var array
     */
    protected $ruleStyles = [];

    /**
     * 起始行号,仅在设置了导入模板后生效,跳过模板原内容
     *
     * @var integer
     */
    protected $startRow = 0;

    /**
     * 模板文件
     *
     * @var string|null
     */
    protected $templateFile = null;

    /**
     * sheet list
     *
     * @var array
     */
    protected $sheets = [];

    /**
     * 数据源
     *
     * @var callable|null
     */
    protected $fillDataFn = null;

    /**
     * @param Excel $excel
     */
    public function __construct(protected Excel $excel)
    {
    }

    /**
     * 设置校验规则列对应的提示语
     *
     * @param string $rule
     * @param callable $comment
     * @return self
     */
    public function setRuleComment(string $rule, callable $comment)
    {
        $this->ruleComments[$rule] = $comment;

        return $this;
    }

    /**
     * 设置校验规则列对应的样式
     *
     * @param string $rule
     * @param array $style
     * @return self
     */
    public function setRuleStyle(string $rule, array $style)
    {
        $this->ruleStyles[$rule] = $style;

        return $this;
    }

    /**
     * 设置导入sheet
     *
     * @param int $index
     * @param string|null $title
     */
    public function setImportSheet(int $index, string $title = null)
    {
        $this->importSheetIndex = $index;
        $this->importSheetTitle = $title ?: '导入模板';
        // 手动维护sheet index与sheet name的关联
        $this->sheets = [$this->importSheetIndex => $this->importSheetTitle];
    }

    /**
     * 设置导入列名
     *
     * @param array $columns
     * @param array $rules
     * @param array $remarks
     */
    public function setFirstColumn(array $columns, array $rules = [], array $remarks = [])
    {
        $this->columns = $columns;
        $this->rules   = $rules;
        $this->remarks = $remarks;
    }

    /**
     * 设置字典
     *
     * @param array<string, Dictionary> $dictionaries
     * @param string|null $title
     */
    public function setDictionaries(array $dictionaries, string $title = null)
    {
        $this->dictionaries   = $dictionaries;
        $this->dictSheetTitle = $title ?: '枚举列可导入内容';
    }

    /**
     * 设置允许使用下拉选项的列
     *
     * @param array $columns
     */
    public function setOptionalColumns(array $columns)
    {
        foreach ($columns as $column) {
            $this->optionalColumns[$column] = true;
        }
    }

    /**
     * 设置需要格式的列
     *
     * @param array $formatColumns
     * @return void
     */
    public function setColumnFormat(array $formatColumns)
    {
        $this->formatColumns = $formatColumns;
    }

    /**
     * 在excel第三个sheet中生成示例(自动生成和模板一样的首行)
     *
     * @param array $example
     */
    public function setExample(array $example)
    {
        $this->example = $example;
    }

    /**
     * 设置模板文件
     *
     * @param string $filename
     * @param int $sheetIndex
     */
    public function setTemplateFile(string $filename, int $sheetIndex = 0)
    {
        // TODO
        // 可以尝试通过合并xml实现,记录cell值在原集合的位置
        // 将数据合并至sharedStrings作为新集合,再把旧集合的位置替换为新集合的位置
    }

    /**
     * 给导入模板填入基础数据
     *
     * @param Builder|iterable $source
     * @param ?int $sheetIndex
     * @param int $startColumn
     * @param int $startRow
     */
    public function fillSimpleData(
        $source,
        ?int $sheetIndex = null,
        int $startColumn = 0,
        int $startRow = 2
    ) {
        if ($source instanceof Builder || $source instanceof EloquentBuilder) {
            $source = $source->cursor();
        }

        if (!is_iterable($source)) {
            throw new TemplateException('无效的数据源');
        }

        $this->fillDataFn = function () use ($source, $sheetIndex, $startRow) {
            if ($sheetIndex === null) {
                $sheetIndex = $this->importSheetIndex;
            }

            // 填充数据
            $this->writeRows($sheetIndex, $source, ($startRow + $this->startRow) - 1);
        };
    }

    /**
     * 将文件保存到指定位置
     *
     * @param string $filename
     */
    public function save(string $filename)
    {
        $this->excel->constMemory($filename, $this->importSheetTitle, false);

        $this->generateColumns()
            ->generateExampleSheet()
            ->generateOptionalColumns();

        if ($this->fillDataFn !== null) {
            call_user_func($this->fillDataFn);
        }

        $this->excel->output();
    }

    /**
     * 生成导入列
     *
     * @return self
     */
    protected function generateColumns()
    {
        // 生成首行信息
        $this->generateFirstColumn($this->importSheetTitle);

        return $this;
    }

    /**
     * 生成首行信息
     *
     * @param string $sheetName
     */
    protected function generateFirstColumn(string $sheetName)
    {
        $this->excel->checkoutSheet($sheetName);

        $currentColumn = 0;
        $currentLine   = $this->startRow;
        foreach ($this->columns as $column => $displayName) {
            $rules = [];
            if (!empty($this->rules[$column])) {
                [$suffix, $rules] = $this->getRuleComment($this->rules[$column]);

                if (!empty($suffix)) {
                    $displayName = "{$displayName}({$suffix})";
                }
            }

            $handle = null;
            // 获取单元格样式
            foreach ($this->ruleStyles as $rule => $options) {
                if (!array_key_exists($rule, $rules)) {
                    continue;
                }

                $style = new Format($this->excel->getHandle());
                foreach ($options as $method => $option) {
                    $style->{$method}(...$option);
                }

                $handle = $style->toResource();
            }

            // 获取内容格式
            $format = $this->formatColumns[$column] ?? NumberFormat::FORMAT_TEXT;

            $this->excel->insertText($currentLine, $currentColumn, $displayName, $format, $handle);

            // 填写字段备注信息
            if (isset($this->remarks[$column])) {
                $this->excel->insertComment($currentLine, $currentColumn, $this->remarks[$column]);
            }

            $currentColumn++;
        }
    }

    /**
     * @param array|string $rules
     * @return array
     */
    protected function getRuleComment(array|string $rules): array
    {
        if (empty($rules)) {
            return [];
        }

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        // 获取校验规则以及变量
        $rules = array_reduce($rules, function ($result, $rule) {
            list($rule, $params) = ValidationRuleParser::parse($rule);

            $result[$rule] = $params;

            return $result;
        }, []);

        $suffix = [];
        foreach ($rules as $rule => $params) {
            if (!array_key_exists($rule, $this->ruleComments)) {
                continue;
            }

            $suffix[] = $this->ruleComments[$rule]($rule, $params, $rules);
        }

        return [join(',', $suffix), $rules];
    }

    /**
     * 给对应的列生成下拉字典
     *
     * @return self
     */
    protected function generateOptionalColumns()
    {
        if (null === $this->importSheetIndex) {
            throw new TemplateException('只有在导入表头加载完成后才允许生成字典');
        }

        $this->excel->checkoutSheet($this->importSheetTitle);

        $index     = 0;
        $maxLine   = 0;
        $dictIndex = 0;
        $columns   = [];
        foreach ($this->columns as $column => $_) {
            $index++;

            if (empty($this->dictionaries[$column])) {
                continue;
            }

            $line             = 0;
            $columns[$column] = [];
            foreach ($this->dictionaries[$column]->all() as $key => $value) {
                $columns[$column][$line++] = $key;
            }

            $dictIndex++;
            $maxLine = max($maxLine, $line);

            if (empty($this->optionalColumns[$column])) {
                continue;
            }

            $validation = new Validation();
            $validation->validationType(Validation::TYPE_LIST_FORMULA)
                ->valueFormula($this->getFormula(
                    $this->dictSheetTitle,
                    $this->stringFromColumnIndex($dictIndex),
                    count($columns[$column]) + 1,
                ));

            $column = $this->stringFromColumnIndex($index);
            // 给1~200000行设置下拉选项
            $this->excel->validation("{$column}2:{$column}200000", $validation->toResource());
        }

        $this->generateDictSheet($columns, $maxLine, $this->dictSheetTitle);

        return $this;
    }

    /**
     * 在excel第二个sheet中生成字典
     *
     * @param array $columns
     * @param integer $maxLine
     * @param string $title
     * @return void
     */
    protected function generateDictSheet(array $columns, int $maxLine, string $title)
    {
        if (empty($columns)) {
            return;
        }

        // 生成首行信息
        $first = [];
        foreach ($columns as $column => $_) {
            $first[] = $this->columns[$column];
        }

        $rows = [$first];

        for ($line = 0; $line < $maxLine; $line++) {
            $row = [];
            foreach ($columns as $column => $dict) {
                if (!empty($dict[$line])) {
                    array_push($row, $dict[$line]);
                } else {
                    array_push($row, null);
                }
            }

            $rows[] = $row;
        }

        $this->writeRows($this->addSheet($title), $rows);
    }

    /**
     * 生成示例sheet
     *
     * @return self
     */
    protected function generateExampleSheet()
    {
        if (null === $this->importSheetIndex) {
            throw new TemplateException('只有在导入表头加载完成后才允许生成示例');
        }

        if (empty($this->example)) {
            return $this;
        }

        $title = '导入示例';
        $index = $this->addSheet($title);
        // 生成首行信息
        $this->generateFirstColumn($title);
        // 添加演示模板数据
        $this->writeRows($index, $this->example, 1);

        return $this;
    }

    /**
     * 设置格式
     *
     * @param string $title
     * @param string $column
     * @param integer $endLine
     * @return string
     */
    protected function getFormula(string $title, string $column, int $endLine): string
    {
        return str_replace(
            ['{title}', '{column}', '{endLine}'],
            [$title, $column, $endLine],
            '{title}!${column}$2:${column}${endLine}'
        );
    }

    /**
     * 对数字做26进制转换
     *
     * @param integer $index
     * @return string
     */
    protected function stringFromColumnIndex(int $index): string
    {
        // phpoffice从1开始计算索引, VtifulExcel从0开始计算索引
        return Excel::stringFromColumnIndex($index - 1);
    }

    /**
     * 获取sheet位置
     *
     * @param string $sheetName
     * @return int
     */
    protected function addSheet(string $sheetName)
    {
        foreach ($this->sheets as $i => $name) {
            if ($name === $sheetName) {
                return $i;
            }
        }

        $this->sheets[] = $sheetName;

        $this->excel->addSheet($sheetName);

        return count($this->sheets) - 1;
    }

    /**
     * 填入基础数据
     *
     * @param int $sheetIndex
     * @param iterable $source
     * @param int $startColumn
     * @param int $startRow
     */
    protected function writeRows(int $sheetIndex, iterable $source, int $startRow = 0)
    {
        if (empty($this->sheets[$sheetIndex])) {
            throw new TemplateException("Sheet Index {{$sheetIndex}}不存在");
        }

        $this->excel->checkoutSheet($this->sheets[$sheetIndex]);
        $this->excel->setCurrentLine($startRow);

        foreach ($source as $row) {
            $this->excel->data([$row]);
        }
    }
}
