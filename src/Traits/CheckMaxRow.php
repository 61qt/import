<?php

namespace QT\Import\Traits;

use QT\Import\Exceptions\MaxRowQuantityException;

/**
 * 可导入最大行校验
 *
 * @package QT\Import\Traits
 */
trait CheckMaxRow
{
    /**
     * 可导入行数最大上限
     *
     * @var int
     */
    protected $maxRowQuantity = 5000;

    /**
     * 检查行数时会跳过的行数
     * 默认跳过1行,因为首行为列名,第二行才是要导入数据
     *
     * @var int
     */
    protected $skipLine = 1;

    /**
     * 检查是否超过最大行限制
     *
     * @param int $line
     * @throws MaxRowQuantityException
     */
    protected function checkMaxRow(int $line)
    {
        if ($line > $this->maxRowQuantity + $this->skipLine) {
            throw new MaxRowQuantityException($this->getMaxRowQuantityError());
        }
    }

    /**
     * 获取最大行报错信息
     *
     * @return string
     */
    protected function getMaxRowQuantityError()
    {
        return sprintf('Excel 最大行数超过%d，请把行数减少至%d以内后，重新上传', $this->maxRowQuantity, $this->maxRowQuantity);
    }
}
