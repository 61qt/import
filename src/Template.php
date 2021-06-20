<?php

namespace QT\Import;

use RuntimeException;
use QT\Import\Contracts\Dictionary;
use Illuminate\Database\Query\Builder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Validation\ValidationRuleParser;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Validation\Concerns\ReplacesAttributes;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Import Template
 *
 * @package QT\Import
 */
class Template
{
    use ReplacesAttributes;

    protected $spreadsheet;

    protected $columnsSheetIndex;

    /**
     * 列名
     *
     * @var array
     */
    protected $columns;

    /**
     * 每一列的校验规则
     *
     * @var array
     */
    protected $rules;

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
     * 校验提示语
     *
     * @var array
     */
    protected $ruleMaps = [
        'Required'   => '必填',
        'Integer'    => '数字',
        'DateFormat' => '格式为 :format',
        'Min'        => [
            'Integer' => '最小为 :min',
            'String'  => '最短为 :min',
        ],
        'Max'        => [
            'Integer' => '最大为 :max',
            'String'  => '最长为 :max',
        ],
        "Between"    => [
            "Integer" => "数值范围 :min - :max 之间。",
            "Numeric" => "数值范围 :min - :max 之间。",
            "String"  => "必须介于 :min - :max 个字符之间。",
        ],
    ];

    /**
     * @param array $column
     * @param array $rules
     * @param array $remarks
     */
    public function __construct(
        array $columns,
        array $rules,
        array $remarks = []
    ) {
        $this->spreadsheet = new Spreadsheet;
        $this->columns     = $columns;
        $this->rules       = $rules;
        $this->remarks     = $remarks;
    }

    /**
     * 生成导入列
     *
     * @param int $sheetIndex
     */
    public function generateColumns(int $sheetIndex = 0)
    {
        if ($sheetIndex === 0) {
            $sheet = $this->spreadsheet->getSheet($sheetIndex);
        } else {
            $sheet = $this->spreadsheet->createSheet($sheetIndex);
        }

        $sheet->setTitle('导入模板');
        // 生成首行信息
        $this->generateFirstColumn($sheet);

        $this->columnsSheetIndex = $sheetIndex;

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
        foreach ($this->columns as $column => $displayName) {
            $coordinate = Coordinate::stringFromColumnIndex(++$currentColumn);

            $rules = [];
            if (!empty($this->rules[$column])) {
                [$suffix, $rules] = $this->getRuleComment($this->rules[$column]);

                if (!empty($suffix)) {
                    $displayName = "{$displayName}({$suffix})";
                }
            }

            $sheet->getCell("{$coordinate}1")->setValue($displayName);
            // 填写字段备注信息
            if (isset($this->remarks[$column])) {
                $text = new RichText;
                $text->createText($this->remarks[$column]);

                $sheet->getComment("{$coordinate}1")->setText($text);
            }

            // TODO 允许根据校验规则设置样式 {required: styles}
            if (array_key_exists('Required', $rules)) {
                // 必填参数背景设置为红色,字体颜色设置为白色,边框颜色设置为黑色
                $sheet->getStyle("{$coordinate}1")->applyFromArray([
                    'fill'    => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => Color::COLOR_RED],
                    ],
                    'font'    => [
                        'color' => ['argb' => Color::COLOR_WHITE],
                    ],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => Color::COLOR_BLACK],
                        ],
                    ],
                ]);
            }
        }
    }

    /**
     * @param array|string $rules
     * @return array
     */
    protected function getRuleComment($rules)
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
            if (!array_key_exists($rule, $this->ruleMaps)) {
                continue;
            }

            $comment = $this->ruleMaps[$rule];

            if (!is_array($comment)) {
                $value = $params[0] ?? '';

                if (method_exists($this, $replacer = "replace{$rule}")) {
                    $suffix[] = $this->$replacer($comment, '', $rule, $params);
                } else {
                    $suffix[] = str_replace(":{$rule}", $value, $comment);
                }
                continue;
            }

            foreach ($comment as $key => $msg) {
                if (!array_key_exists($key, $rules)) {
                    continue;
                }

                $value = $params[0] ?? '';
                if (method_exists($this, $replacer = "replace{$rule}")) {
                    $suffix[] = $this->$replacer($msg, '', $rule, $params);
                } else {
                    $suffix[] = str_replace(":{$rule}", $value, $msg);
                }
            }
        }

        return [join(',', $suffix), $rules];
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
            throw new RuntimeException('无效的数据源');
        }

        $sheet = $this->spreadsheet->getSheet($sheetIndex);
        // 填充数据
        $this->addStrictStringRows($sheet, $source, $startColumn, $startRow);
    }

    /**
     * 设置允许使用下拉选项的列
     *
     * @param array<string, Dictionary> $dictionaries
     */
    public function setOptionalColumns(array $dictionaries)
    {
        if (null === $this->columnsSheetIndex) {
            throw new RuntimeException('只有在导入表头加载完成后才允许生成字典');
        }

        // 保证字典与导入sheet同步
        $sheet = $this->spreadsheet->getSheet($this->columnsSheetIndex);

        $columns = array_keys($this->columns);
        foreach ($dictionaries as $column => $dictionary) {
            $columnIndex = array_search($column, $columns);

            if ($columnIndex === false) {
                continue;
            }

            // todo 根据是否必填检查是否允许为空
            $validation = (new DataValidation)
                ->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('输入错误')
                ->setError("必须在可选的范围内")
                ->setFormula1('"' . join(',', $dictionary->keys()) . '"');

            $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
            // 给1~200000行设置下拉选项
            $sheet->setDataValidation("{$column}2:{$column}200000", $validation);
        }
    }

    /**
     * 在excel第二个sheet中生成字典
     *
     * @param array<string, Dictionary> $dictionaries
     */
    public function generateDictSheet($dictionaries)
    {
        if (null === $this->columnsSheetIndex) {
            throw new RuntimeException('只有在导入表头加载完成后才允许生成字典');
        }

        $maxLine = 0;
        $columns = [];
        // 检查那些导入列需要设置字典
        foreach (array_keys($this->columns) as $column) {
            if (empty($dictionaries[$column])) {
                continue;
            }

            $line = 0;
            $dict = [];
            foreach ($dictionaries[$column]->all() as $key => $value) {
                $dict[$line++] = [$key, $value];
            }

            $maxLine = max($maxLine, $line);

            $columns[$column] = $dict;
        }

        if (empty($columns)) {
            return;
        }

        // 获取导入用的sheet后一个sheet
        $sheet = $this->spreadsheet->createSheet(
            $this->spreadsheet->getActiveSheetIndex() + 1
        );

        // 生成首行信息
        $first = [];
        foreach (array_keys($columns) as $column) {
            $first[] = "{$this->columns[$column]}字典";
            $first[] = "字典含义";
        }

        $rows = [$first];
        for ($line = 0; $line < $maxLine; $line++) {
            $row = [];
            foreach ($columns as $column => $dict) {
                if (!empty($dict[$line])) {
                    array_push($row, ...$dict[$line]);
                } else {
                    array_push($row, null, null);
                }
            }

            $rows[] = $row;
        }

        $sheet->setTitle('导入字典');
        $this->addStrictStringRows($sheet, $rows);
    }

    /**
     * 将文件保存到指定位置
     *
     * @param string $filename
     */
    public function save(string $filename)
    {
        $this->spreadsheet->setActiveSheetIndex($this->columnsSheetIndex);

        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        $writer->save($filename);
    }

    /**
     * 填入基础数据
     *
     * @param Worksheet $sheet
     * @param \Iterable $source
     * @param int $startColumn
     * @param int $startRow
     */
    protected function addStrictStringRows($sheet, $source, $startColumn = 0, $startRow = 1)
    {
        foreach ($source as $row) {
            $currentColumn = $startColumn;
            foreach ($row as $value) {
                $sheet->getCellByColumnAndRow(++$currentColumn, $startRow)
                    ->setValueExplicit($value, DataType::TYPE_STRING);
            }
            ++$startRow;
        }
    }
}