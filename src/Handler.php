<?php

namespace QT\Import;

use Iterator;
use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderInterface;
use QT\Import\Contracts\WithMultipleSheets;
use QT\Import\Exceptions\SheetNotFoundException;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Manager\OptionsManagerInterface;

/**
 * Handler
 *
 * @package QT\Import
 */
class Handler
{
    /**
     * @param array $readOptions
     */
    public function __construct(protected array $readOptions = [])
    {
    }

    /**
     * 执行导入逻辑
     *
     * @param Task $task
     * @param string $filename
     * @param array $input
     */
    public function import(Task $task, string $filename, array $input = [])
    {
        $reader = $this->getReader(Type::XLSX);
        $reader->open($filename);

        $sheets = $this->buildSheetTasks($reader->getSheetIterator(), $task, $input);

        foreach ($sheets as [$task, $rows]) {
            $task->handle($rows, $input);
        }
    }

    /**
     * 获取文件读取器
     *
     * @param string $type
     * @return ReaderInterface
     */
    public function getReader(string $type): ReaderInterface
    {
        $reader = ReaderFactory::createFromType($type);

        if ($reader instanceof OptionsManagerInterface) {
            foreach ($this->readOptions as $name => $value) {
                $reader->setOption($name, $value);
            }
        }

        return $reader;
    }

    /**
     * @param Iterator $sheetIterator
     * @param Task $task
     * @param array $input
     * @return array
     */
    protected function buildSheetTasks(Iterator $sheetIterator, Task $task, array $input): array
    {
        $sheets = [];
        $fields = $task->getFields($input);
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
