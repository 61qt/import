<?php

use QT\Import\Contracts\Dictionary;

include __DIR__."/../vendor/autoload.php";

class Dict implements Dictionary
{
    protected $maps = [];

    public function __construct(array $maps)
    {
        $this->maps = $maps;
    }

    public function has(string|int $key): bool
    {
        return isset($this->maps[$key]);
    }

    public function get(string|int $key): string|int|null
    {
        return $this->maps[$key] ?? null;
    }

    public function all(): array
    {
        return $this->maps;
    }

    public function keys(): array
    {
        return array_keys($this->maps);
    }

    public function values(): array
    {
        return array_values($this->maps);
    }
}
