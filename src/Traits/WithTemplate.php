<?php

namespace QT\Import\Traits;

use QT\Import\Template;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use QT\Import\Contracts\Template as ContractTemplate;
use Illuminate\Validation\Concerns\ReplacesAttributes;

/**
 * 导入模板
 *
 * @package QT\Import\Traits
 */
trait WithTemplate
{
    use ReplacesAttributes;

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
        'Required'      => '必填',
        'Integer'       => '数字',
        'DateFormat'    => '格式为 :format',
        'Min'           => [
            'Integer' => '最小为 :min',
            'String'  => '最短为 :min',
        ],
        'Max'           => [
            'Integer' => '最大为 :max',
            'String'  => '最长为 :max',
        ],
        'Between'       => [
            'Integer' => '数值范围 :min - :max 之间。',
            'Numeric' => '数值范围 :min - :max 之间。',
            'String'  => '必须介于 :min - :max 个字符之间。',
        ],
        'DigitsBetween' => ' :min 到 :max 位数字',
        'Digits'        => ' :digits 位数字',
    ];

    /**
     * 校验规则样式
     *
     * @var array
     */
    protected $ruleStyles = [
        'Required' => [
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
        ],
    ];

    /**
     * 获取可导入模板文件
     *
     * @param array $input
     * @return Template
     */
    public function getImportTemplate(array $input = []): ContractTemplate
    {
        $fields = $this->getFields($input);

        $path = resource_path("import-template/{$this->importTemplateFile}.xlsx");
        if (!empty($this->importTemplateFile) && file_exists($path)) {
            $reader      = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($path);
        } else {
            $spreadsheet = new Spreadsheet();
        }

        $template = new Template($spreadsheet);

        $template->setImportSheet(0);
        $template->setFirstColumn($fields, $this->rules, $this->remarks);
        $template->setDictionaries($this->getDictionaries());
        $template->setOptionalColumns($this->getOptionalColumns($input));

        foreach ($this->ruleComments as $rule => $comment) {
            $template->setRuleComment($rule, $this->getCommentCallback($comment));
        }

        foreach ($this->ruleStyles as $rule => $style) {
            $template->setRuleStyle($rule, $style);
        }

        return $template;
    }

    /**
     * 获取校验规则对应的备注处理方法
     *
     * @param mixed $comment
     * @return callable
     */
    protected function getCommentCallback(mixed $comment): callable
    {
        return function (string $rule, array $params, array $all) use ($comment) {
            if (is_string($comment)) {
                $message = $comment;
            } else {
                $message = $comment['String'] ?? '';
                foreach ($comment as $key => $msg) {
                    if (array_key_exists($key, $all)) {
                        $message = $msg;
                        break;
                    }
                }
            }

            if (method_exists($this, $replacer = "replace{$rule}")) {
                return $this->{$replacer}($message, '', $rule, $params);
            }

            return str_replace(":{$rule}", $params[0] ?? '', $message);
        };
    }

    /**
     * 获取可选列
     *
     * @param array $input
     * @return array<string, \QT\Import\Contracts\Dictionary>
     */
    public function getOptionalColumns(array $input = []): array
    {
        return $this->optional;
    }
}
