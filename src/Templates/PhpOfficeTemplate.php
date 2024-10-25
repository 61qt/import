<?php

namespace QT\Import\Templates;

use QT\Import\Contracts\Dictionary;
use Illuminate\Database\Query\Builder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use QT\Import\Exceptions\TemplateException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Validation\ValidationRuleParser;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use QT\Import\Contracts\Template as ContractTemplate;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Excel导入模板
 *
 * @package QT\Import\Templates
 */
class PhpOfficeTemplate implements ContractTemplate
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
     * @param Spreadsheet $spreadsheet
     */
    public function __construct(protected Spreadsheet $spreadsheet)
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
        $spreadsheet = IOFactory::createReaderForFile($filename)->load($filename);

        // 计算要跳过的行数
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $flag  = CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL;
        foreach ($sheet->getRowIterator() as $line => $row) {
            if (!$row->isEmpty($flag)) {
                $this->startRow = $line;
            }
        }

        // 复制模板文件到导入模板上
        $this->spreadsheet->addExternalSheet($sheet, $this->importSheetIndex);
        // 去掉复制后多余的sheet
        $this->spreadsheet->removeSheetByIndex($this->importSheetIndex + 1);
    }

    /**
     * 给导入模板填入基础数据
     *
     * @param Builder|iterable $source
     * @param int $sheetIndex
     * @param int $startColumn
     * @param int $startRow
     */
    public function fillSimpleData(
        $source,
        int $sheetIndex = 0,
        int $startColumn = 0,
        int $startRow = 2
    ) {
        if ($source instanceof Builder || $source instanceof EloquentBuilder) {
            $source = $source->cursor();
        }

        if (!is_iterable($source)) {
            throw new TemplateException('无效的数据源');
        }

        $sheet = $this->spreadsheet->getSheet($sheetIndex);
        // 填充数据
        $this->addStrictStringRows($sheet, $source, $startColumn, $startRow + $this->startRow);
    }

    /**
     * 将文件保存到指定位置
     *
     * @param string $filename
     */
    public function save(string $filename)
    {
        $this->generateColumns()
            ->generateOptionalColumns()
            ->generateExampleSheet();

        $this->spreadsheet->setActiveSheetIndex($this->importSheetIndex);

        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        $writer->save($filename);
    }

    /**
     * 生成导入列
     *
     * @return self
     */
    protected function generateColumns()
    {
        if ($this->importSheetIndex === 0) {
            $sheet = $this->spreadsheet->getSheet($this->importSheetIndex);
        } else {
            $sheet = $this->spreadsheet->createSheet($this->importSheetIndex);
        }

        $sheet->setTitle($this->importSheetTitle);
        // 生成首行信息
        $this->generateFirstColumn($sheet);

        return $this;
    }

    /**
     * 生成首行信息
     *
     * @param Worksheet $sheet
     */
    protected function generateFirstColumn(Worksheet $sheet)
    {
        $currentColumn = 0;
        $currentLine   = $this->startRow + 1;
        foreach ($this->columns as $column => $displayName) {
            $coordinate = Coordinate::stringFromColumnIndex(++$currentColumn);

            $rules = [];
            if (!empty($this->rules[$column])) {
                [$suffix, $rules] = $this->getRuleComment($this->rules[$column]);

                if (!empty($suffix)) {
                    $displayName = "{$displayName}({$suffix})";
                }
            }

            $sheet->getCell("{$coordinate}{$currentLine}")->setValue($displayName);

            $format = $this->formatColumns[$column] ?? NumberFormat::FORMAT_TEXT;
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($format);

            // 填写字段备注信息
            if (isset($this->remarks[$column])) {
                $text = new RichText();
                $text->createText($this->remarks[$column]);

                $sheet->getComment("{$coordinate}{$currentLine}")->setText($text);
            }

            foreach ($this->ruleStyles as $rule => $style) {
                if (array_key_exists($rule, $rules)) {
                    $sheet->getStyle("{$coordinate}{$currentLine}")->applyFromArray($style);
                }
            }
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

        $index     = 0;
        $maxLine   = 0;
        $dictIndex = 0;
        $columns   = [];
        $sheet     = $this->spreadsheet->getSheet($this->importSheetIndex);
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

            $validation = (new DataValidation())
                ->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('输入错误')
                ->setError('必须在可选的范围内')
                ->setFormula1($this->getFormula(
                    $this->dictSheetTitle,
                    Coordinate::stringFromColumnIndex($dictIndex),
                    count($columns[$column]) + 1
                ));

            $column = Coordinate::stringFromColumnIndex($index);
            // 给1~200000行设置下拉选项
            $sheet->setDataValidation("{$column}2:{$column}200000", $validation);
        }

        $this->generateDictSheet($columns, $maxLine, $this->dictSheetTitle);

        return $this;
    }

    /**
     * 在excel第二个sheet中生成字典
     *
     * @param array $columns
     * @param integer $maxLine
     * @param string|null $title
     * @return void
     */
    protected function generateDictSheet(array $columns, int $maxLine, string $title = null)
    {
        if (empty($columns)) {
            return;
        }

        // 获取导入用的sheet后一个sheet
        $sheet = $this->spreadsheet->createSheet(
            $this->spreadsheet->getActiveSheetIndex() + 1
        );

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

        $sheet->setTitle($title);

        $this->addStrictStringRows($sheet, $rows);
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

        // 获取导入用的sheet后一个sheet
        $sheet = $this->spreadsheet->createSheet(
            $this->spreadsheet->getActiveSheetIndex() + 1
        );

        $sheet->setTitle('导入示例');
        // 生成首行信息
        $this->generateFirstColumn($sheet);
        // 添加演示模板数据
        $this->addStrictStringRows($sheet, $this->example, 0, 2);

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
     * 填入基础数据
     *
     * @param Worksheet $sheet
     * @param iterable $source
     * @param int $startColumn
     * @param int $startRow
     */
    protected function addStrictStringRows(Worksheet $sheet, iterable $source, int $startColumn = 0, int $startRow = 1)
    {
        foreach ($source as $row) {
            $currentColumn = $startColumn;
            foreach ($row as $value) {
                $coordinate = Coordinate::stringFromColumnIndex(++$currentColumn);

                $sheet->getCell("{$coordinate}{$startRow}")
                    ->setValueExplicit($value, DataType::TYPE_STRING);
            }
            ++$startRow;
        }
    }
}
