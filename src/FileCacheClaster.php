<?php

namespace Inilim\FileCache;

use Closure;
use Inilim\FileCache\FileCache;

/**
 * TODO заменить glob на scandir
 */
class FileCacheClaster extends FileCache
{
    protected const NAME_DIR = 'clasters';

    public function getOrSaveFromClaster(string|int|float $id, string|int|float $claster_name, Closure $data, int $lifetime = 3600): mixed
    {
        $res = $this->getFromClaster($id, $claster_name);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->saveToClaster($id, $claster_name, $res, $lifetime);
        }
        return $res;
    }

    public function getFromClaster(string|int|float $id, string|int|float $claster_name): mixed
    {
        $path_file = $this->getPathFileByIDAndName(\strval($id), \strval($claster_name));
        return $this->read($path_file);
    }

    public function saveToClaster(string|int|float $id, string|int|float $claster_name, mixed $data, int $lifetime = 3600): bool
    {
        $id  = \strval($id);
        $dir = $this->getDirByIDAndName($id, \strval($claster_name));
        if (!$this->createDir($dir)) return false;
        return $this->saveData(
            $dir . self::DIR_SEP . \md5($id, false),
            $data,
            $lifetime
        );
    }

    public function existFromClaster(string|int|float $id, string|int|float $claster_name): bool
    {
        $path_file = $this->getPathFileByIDAndName(\strval($id), \strval($claster_name));
        if (!\is_file($path_file)) return false;
        if (@\filemtime($path_file) > \time()) return true;
        return false;
    }

    public function deleteFromClaster(string|int|float $id, string|int|float $claster_name): void
    {
        $path_file = $this->getPathFileByIDAndName(\strval($id), \strval($claster_name));
        @\unlink($path_file);
    }

    public function deleteAllFromClaster(string|int|float $claster_name): void
    {
        $m_dir = $this->getDirByName(\strval($claster_name));
        $names = $this->getAllNamesFromDir($m_dir);

        if (!$names) return;

        \array_map(
            function ($n) use ($m_dir) {
                // 
                $dir2 = $m_dir . self::DIR_SEP . $n;
                // 
                if (\is_file($dir2)) {
                    @\unlink($dir2);
                    return;
                }
                // 
                \array_map(function ($nn) use ($dir2) {
                    // 
                    $dir3 = $dir2 . self::DIR_SEP . $nn;
                    // 
                    if (\is_file($dir3)) @\unlink($dir3);
                    // 
                }, $this->getAllNamesFromDir($dir2));
                // 
            },
            $names
        );
    }

    public function deleteAll(bool $clasters = false): void
    {
        $m_dir = $this->getCacheDir();
        $names = $this->getAllNamesFromDir($m_dir);

        // берем кластерные папки
        if (!$clasters) {
            $names = \array_merge($names, $this->getAllClasterNames());
        }

        if (!$names) return;

        \array_map(
            function ($n) use ($m_dir) {
                // 
                $dir2 = $m_dir . self::DIR_SEP . $n;
                // 
                if (\is_file($dir2)) {
                    @\unlink($dir2);
                    return;
                }
                // 
                \array_map(function ($nn) use ($dir2) {
                    // 
                    $dir3 = $dir2 . self::DIR_SEP . $nn;
                    // 
                    if (\is_file($dir3)) @\unlink($dir3);
                    // 
                }, $this->getAllNamesFromDir($dir2));
                // 
            },
            $names
        );
    }

    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    /**
     * @return string[]|array{}
     */
    protected function getAllClasterNames(): array
    {
        $dir = $this->getDirClaster();
        $this->getAllNamesFromDir($dir);
        return [];
    }

    /**
     * main_dir/clasters/[a-z]{36}/[a-z]{2}/[a-z]{36} |
     */
    protected function getPathFileByIDAndName(string $id, string $claster_name): string
    {
        return $this->getDirByIDAndName($id, $claster_name) . self::DIR_SEP . \md5($id, false);
    }

    /**
     * main_dir/clasters/[a-z]{36}/[a-z]{2} |
     * отдает только путь до папки где будет хранится файл
     */
    protected function getDirByIDAndName(string $id, string $name): string
    {
        return \implode(self::DIR_SEP, [
            $this->getDirByName($name),
            \substr(\md5($id, false), 0, 2),
        ]);
    }

    /**
     * main_dir/clasters/[a-z]{36} |
     * отдает только путь до папки кластера
     */
    protected function getDirByName(string $name): string
    {
        return \implode(self::DIR_SEP, [
            $this->getDirClaster(),
            \md5($name, false),
        ]);
    }

    /**
     * main_dir/clasters |
     */
    protected function getDirClaster(): string
    {
        return \implode(self::DIR_SEP, [
            $this->getCacheDir(),
            self::NAME_DIR,
        ]);
    }
}
