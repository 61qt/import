<?php

namespace QT\Import;

use Iterator;
use Box\Spout\Common\Type;
use QT\Import\Contracts\WithMultipleSheets;
use QT\Import\Exceptions\SheetNotFoundException;
use Box\Spout\Reader\Common\Creator\ReaderFactory;

/**
 * Handler
 *
 * @package QT\Import
 */
class Handler
{
    /**
     * 执行导入逻辑
     *
     * @param Task $task
     * @param string $filename
     * @param array $input
     */
    public function import(Task $task, string $filename, array $input = [])
    {
        $reader = ReaderFactory::createFromType(Type::XLSX);
        $reader->open($filename);

        $sheets = $this->buildSheetTasks($reader->getSheetIterator(), $task);

        foreach ($sheets as [$task, $rows]) {
            $task->handle($rows, $input);
        }
    }

    /**
     * @param Iterator $sheetIterator
     * @param Task $task
     * @return array
     */
    protected function buildSheetTasks(Iterator $sheetIterator, Task $task): array
    {
        $sheets = [];
        $fields = $task->getFields();
        if ($task instanceof WithMultipleSheets) {
            $nameMap  = [];
            $indexMap = [];
            foreach ($sheetIterator as $sheet) {
                $nameMap[$sheet->getName()]   = $sheet;
                $indexMap[$sheet->getIndex()] = $sheet;
            }

            foreach ($task->sheets() as $index => $subTask) {
                $map = is_int($index) ? $indexMap : $nameMap;

                if (!isset($map[$index])) {
                    throw new SheetNotFoundException("Sheet[{$index}] 不存在");
                }

                $sheets[] = [$subTask, new Rows($map[$index], $fields)];
            }
        } else {
            $sheetIterator->rewind();

            $sheets[] = [$task, new Rows($sheetIterator->current(), $fields)];
        }

        return $sheets;
    }
}
