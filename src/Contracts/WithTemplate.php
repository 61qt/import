<?php

namespace QT\Import\Contracts;

use QT\Import\Template;

/**
 * WithTemplate
 * @package QT\Import\Contracts
 */
interface WithTemplate
{
    /**
     * 获取可导入模板文件
     *
     * @param array $input
     * @return Template
     */
    public function getImportTemplate(array $input = []): Template;
}
