<?php

namespace QT\Import\Traits;

use QT\Import\Template;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

trait WithTemplate
{
    /**
     * 字段备注信息
     *
     * @var array
     */
    protected $remarks = [];

    /**
     * 下拉可选列
     *
     * @var array
     */
    protected $optional = [];

    /**
     * 校验规则提示语
     *
     * @var array
     */
    protected $ruleComments = [
        'Required' => '必填',
        'Integer'  => '数字',
        'Date'     => '格式为 年/月/日',
        'Min'      => [
            'Integer' => '最小为 :min',
            'String'  => '最短为 :min',
        ],
        'Max' => [
            'Integer' => '最大为 :max',
            'String'  => '最长为 :max',
        ],
        "Between" => [
            "Integer" => "数值范围 :min - :max 之间。",
            "Numeric" => "数值范围 :min - :max 之间。",
            "String"  => "必须介于 :min - :max 个字符之间。",
        ],
    ];

    /**
     * 校验规则样式
     *
     * @var array
     */
    protected $ruleStyles = [
        'Required' => [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => Color::COLOR_RED],
            ],
            'font' => [
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => Color::COLOR_BLACK],
                ],
            ],
        ],
    ];

    /**
     * 获取可导入模板文件
     *
     * @param array $input
     * @return Template
     */
    public function getImportTemplate(array $input = []): Template
    {
        $template = new Template(new Spreadsheet());

        $template->setImportSheet(0);
        $template->setFirstColumn($this->getFields(), $this->rules, $this->remarks);
        $template->setOptionalColumns($this->getOptionalColumns($input));

        foreach ($this->ruleComments as $rule => $comment) {
            $template->setRuleComment($rule, $comment);
        }

        foreach ($this->ruleStyles as $rule => $style) {
            $template->setRuleStyle($rule, $style);
        }

        return $template;
    }

    /**
     * 获取可选列
     *
     * @param array $input
     * @return array<string, \QT\Import\Contracts\Dictionary>
     */
    public function getOptionalColumns(array $input = []): array
    {
        $optional = [];
        foreach ($this->optional as $field) {
            $dictionary = $this->getDictionary($field);

            if ($dictionary === null) {
                continue;
            }

            $optional[$field] = $dictionary;
        }

        return $optional;
    }
}
