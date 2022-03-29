<?php

namespace QT\Import\Traits;

use QT\Import\Template;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use QT\Import\Contracts\Template as ContractsTemplate;

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
     * 获取可导入模板文件
     *
     * @param array $inpu
     * @return ContractsTemplate
     */
    public function getImportTemplate(array $input = []): ContractsTemplate
    {
        $template = new Template(new Spreadsheet(), $this->rules, $this->remarks);

        $template->setImportSheet(0);
        $template->setFirstColumn($this->getFields());
        $template->setOptionalColumns($this->getOptionalColumns($input));

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
