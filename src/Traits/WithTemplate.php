<?php

namespace QT\Import\Traits;

use Vtiful\Kernel\Excel;
use QT\Import\Contracts\Template;
use QT\Import\Templates\VtifulTemplate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use QT\Import\Templates\PhpOfficeTemplate;
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
     * 模板生成方式
     *
     * @var string
     */
    protected $templateDriver = 'vtiful';

    /**
     * 字段备注信息
     *
     * @var array
     */
    protected $remarks = [];

    /**
     * 设置需要格式的列
     * 如：['column' => NumberFormat::FORMAT_GENERAL]
     *
     * @var array
     */
    protected $formatColumns = [];

    /**
     * 校验规则样式
     *
     * @var array
     */
    protected $ruleStyles = [];

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
     * 获取导出模板
     *
     * @param string $driver
     * @return Template|VtifulTemplate|PhpOfficeTemplate
     */
    protected function getImportTemplate(string $driver)
    {
        $this->ruleStyles = match ($driver) {
            /**
             * 可设置内容参考文档与实现
             * @see https://xlswriter-docs.viest.me/zh-cn/yang-shi-lie-biao
             * @see https://github.com/viest/php-ext-xlswriter/blob/master/kernel/format.c
             */
            'vtiful'    => [
                'Required' => [
                    'background' => [\Vtiful\Kernel\Format::COLOR_RED, \Vtiful\Kernel\Format::PATTERN_SOLID],
                    'fontColor'  => [\Vtiful\Kernel\Format::COLOR_WHITE],
                    'border'     => [\Vtiful\Kernel\Format::BORDER_THIN],
                ],
            ],
            'phpoffice' => [
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
            ],
        };

        return match ($driver) {
            'vtiful'    => new VtifulTemplate(new Excel(['path' => '/'])),
            'phpoffice' => new PhpOfficeTemplate(new Spreadsheet()),
        };
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
}
