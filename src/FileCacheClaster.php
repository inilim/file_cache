<?php

namespace Inilim\FileCache;

use Closure;
use Inilim\FileCache\FileCache;

/**
 */
class FileCacheClaster extends FileCache
{
    protected const NAME_DIR = '_clasters';

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function getOrSaveFromClaster($id, $claster_name, Closure $data, int $lifetime = 3600): mixed
    {
        $res = $this->getFromClaster($id, $claster_name);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->saveToClaster($id, $claster_name, $res, $lifetime);
        }
        return $res;
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function getFromClaster($id, $claster_name): mixed
    {
        return $this->read(
            $this->getPathFileByIDAndName(\serialize($id), \serialize($claster_name))
        );
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     * @param mixed $data
     */
    public function saveToClaster($id, $claster_name, $data, int $lifetime = 3600): bool
    {
        $hash  = \md5(\serialize($id), false);
        $dir = $this->getDirByIDAndName($hash, \serialize($claster_name));
        if (!$this->createDir($dir)) return false;
        return $this->saveData(
            $dir . self::DIR_SEP . $hash,
            $data,
            $lifetime
        );
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function existFromClaster($id, $claster_name): bool
    {
        $path_file = $this->getPathFileByIDAndName(\serialize($id), \serialize($claster_name));
        if (!\is_file($path_file)) return false;
        if (\filemtime($path_file) > \time()) return true;
        return false;
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function deleteFromClaster($id, $claster_name): void
    {
        @\unlink(
            $this->getPathFileByIDAndName(\serialize($id), \serialize($claster_name))
        );
    }

    /**
     * @param mixed $claster_name
     */
    public function deleteAllFromClaster($claster_name): void
    {
        $this->deleteAllFromDir(
            $this->getDirByName(\serialize($claster_name))
        );
    }

    public function deleteAllClasters(): void
    {
        $this->deleteAllFromDir(
            $this->getDirClaster()
        );
    }
    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    /**
     * на рефакторинг
     */
    protected function deleteAllFromDir(string $dir): void
    {
        if (!\is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $dirs = [];
        // удаляем файлы
        foreach ($it as $dir_or_file) {
            /** @var \SplFileInfo $dir_or_file */
            $pathname = $dir_or_file->getPathname();
            if (\is_dir($pathname)) $dirs[] = $pathname;
            else @\unlink($pathname);
        }
        // INFO сортируем от дочерних к родителям
        \usort($dirs, function ($a, $b) {
            $la = \strlen($a);
            $lb = \strlen($b);
            if ($la < $lb) return 1;
            elseif ($la == $lb) return 0;
            return -1;
        });
        // удаляем директории
        foreach ($dirs as $dir) @\rmdir($dir);
    }

    /**
     * main_dir_{clasters}/[a-z]{36}/[a-z]{2}/[a-z]{36} |
     */
    protected function getPathFileByIDAndName(string $id, string $claster_name): string
    {
        $hash = \md5($id, false);
        return $this->getDirByIDAndName($hash, $claster_name) . self::DIR_SEP . $hash;
    }

    /**
     * main_dir_{clasters}/[a-z]{36}/[a-z]{2} |
     * отдает только путь до папки где будет хранится файл
     */
    protected function getDirByIDAndName(string $id_hash, string $claster_name): string
    {
        return $this->getDirByName($claster_name) .
            self::DIR_SEP .
            \substr($id_hash, 0, 2);
    }

    /**
     * main_dir_{clasters}/[a-z]{36} |
     * отдает только путь до папки кластера
     */
    protected function getDirByName(string $claster_name): string
    {
        return $this->getDirClaster() .
            self::DIR_SEP .
            \md5($claster_name, false);
    }

    /**
     * main_dir{_clasters} |
     */
    protected function getDirClaster(): string
    {
        return $this->cache_dir . self::NAME_DIR;
    }
}
