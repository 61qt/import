<?php

namespace QT\Import\Foundation;

use RuntimeException;
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
use Illuminate\Validation\Concerns\ReplacesAttributes;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * 导入模板
 *
 * Class ImportTemplate
 * @package App\Tasks\Import\Templates
 */
class ImportTemplate
{
    use ReplacesAttributes;

    protected $spreadsheet;

    protected $columns;

    protected $rules;

    protected $remarks = [];

    protected $extraDictFields = [];

    protected $columnsSheetIndex;

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
        array $remarks = [],
        array $extraDictFields = []
    ) {
        $this->spreadsheet     = new Spreadsheet;
        $this->columns         = $columns;
        $this->rules           = $rules;
        $this->remarks         = $remarks;
        $this->extraDictFields = $extraDictFields;
    }

    /**
     * 生成模板首行信息
     */
    public function generateColumns($sheetIndex = 0)
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

    protected function generateFirstColumn($sheet)
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
        $sheetIndex = 0,
        $startColumn = 0,
        $startRow = 2
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
     * 将文件保存到指定位置
     *
     * @filename
     */
    public function save($filename)
    {
        $this->spreadsheet->setActiveSheetIndex($this->columnsSheetIndex);

        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        $writer->save($filename);
    }

    /**
     * 填入基础数据
     *
     * @param Builder|iterable $source
     * @param int $sheetIndex
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
