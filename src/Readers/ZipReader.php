<?php

namespace QT\Import\Readers;

use Iterator;
use Generator;
use ZipArchive;
use SplFileInfo;
use RegexIterator;
use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveRegexIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Class ZipReader
 *
 * @package QT\Import\Readers
 */
class ZipReader implements Iterator
{
    /**
     * 文件迭代器
     *
     * @var Iterator
     */
    protected $files;

    /**
     * 解压路径
     *
     * @var string
     */
    protected $extractPath;

    /**
     * @param string $filename
     * @param string $regex
     */
    public function __construct(string $filename, protected ?string $regex = null)
    {
        // 记录解压文件夹
        $this->extractPath = sprintf('%s/zip-%s', sys_get_temp_dir(), Str::random(8));

        $this->extractTo($filename, $this->extractPath);
    }

    /**
     * 销毁对象时清理解压文件
     *
     * @return void
     */
    public function __destruct()
    {
        $this->deleteDirectory($this->extractPath);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->files = $this->getFileIterator($this->extractPath, $this->regex);
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->files->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        $this->files->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int
    {
        return $this->files->key();
    }

    /**
     * {@inheritdoc}
     */
    public function current(): array
    {
        $file      = $this->files->current();
        $filename  = $file->getFilename();
        $extension = $file->getExtension();

        return [$file->getPathname(), substr($filename, 0, -(strlen($extension) + 1))];
    }

    /**
     * @param string $path
     * @param string $regex
     * @return Generator
     */
    public function getFileIterator(string $path, ?string $regex = null)
    {
        // 获取文件内所有文件,并将这些文件整合至同一个迭代器内
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        if ($regex !== null) {
            // 只获取符合匹配规则的文件
            $iterator = new RegexIterator($iterator, $regex, RecursiveRegexIterator::GET_MATCH);
        }

        $line = 1;
        foreach ($iterator as $name => $file) {
            if (!$file instanceof SplFileInfo) {
                $file = new SplFileInfo($name);
            }

            $name = $file->getBasename();

            if (in_array($name, ['.', '..', '.DS_Store'])) {
                continue;
            }

            yield $line++ => $file;
        }
    }

    /**
     * @param string $openPath
     * @param string $extractPath
     * @return void
     */
    protected function extractTo(string $openPath, string $extractPath)
    {
        $zip = $this->renameNonUtf8Files(new ZipArchive(), $openPath);

        $zip->extractTo($extractPath);
        $zip->close();
    }

    /**
     * 重命名非UTF-8编码的文件
     *
     * @param ZipArchive $zip
     * @param string $openPath
     * @return ZipArchive
     */
    private function renameNonUtf8Files(ZipArchive $zip, string $openPath): ZipArchive
    {
        $zip = new ZipArchive();
        $zip->open($openPath, ZipArchive::CREATE);

        $isUtf8 = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $oldName  = $zip->getNameIndex($i, ZipArchive::FL_ENC_RAW);
            $encoding = mb_detect_encoding($oldName, ['UTF-8', 'GB2312', 'GBK']);

            if ($encoding !== 'UTF-8' && $newName = iconv($encoding, 'UTF-8', $oldName)) {
                $isUtf8 = false;
                $zip->renameIndex($i, $newName);
            }
        }

        if (!$isUtf8) {
            $zip->close();
            $zip = new ZipArchive();
            $zip->open($openPath, ZipArchive::CREATE);
        }

        return $zip;
    }

    /**
     * 清理解压文件夹
     *
     * @param string $directory
     * @return void
     */
    public function deleteDirectory(string $directory)
    {
        // 获取文件内所有文件,并将这些文件整合至同一个迭代器内
        $items = new FilesystemIterator($directory);

        foreach ($items as $item) {
            $filename = $item->getPathname();

            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($filename);
            } else {
                if (@unlink($filename)) {
                    clearstatcache(false, $filename);
                }
            }
        }

        @rmdir($directory);
    }
}
