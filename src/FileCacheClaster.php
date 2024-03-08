<?php

namespace Inilim\FileCache;

use Closure;
use Inilim\FileCache\FileCache;

/**
 */
class FileCacheClaster extends FileCache
{
    protected const NAME_DIR = 'clasters';

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
        $path_file = $this->getPathFileByIDAndName(\serialize($id), \serialize($claster_name));
        return $this->read($path_file);
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
        $path_file = $this->getPathFileByIDAndName(\serialize($id), \serialize($claster_name));
        @\unlink($path_file);
    }

    /**
     * @param mixed $claster_name
     */
    public function deleteAllFromClaster($claster_name): void
    {
        $m_dir = $this->getDirByName(\serialize($claster_name));
        $d = new \RecursiveDirectoryIterator($m_dir, \FilesystemIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($d, \RecursiveIteratorIterator::SELF_FIRST);
        $dirs = [];
        // удаляем файлы
        foreach ($it as $dir_or_file) {
            /** @var \SplFileInfo $dir_or_file */
            $pathname = $dir_or_file->getFilename();
            if (\is_dir($pathname)) $dirs[] = $pathname;
            else @\unlink($pathname);
        }
        // удаляем директории
        foreach ($dirs as $dir) @\rmdir($dir);
    }

    public function deleteAll(bool $clasters = false): void
    {
        if (!$clasters) {
            parent::deleteAll();
            return;
        }
        // файлы
        // TODO в текущем классе родительский deleteAll удалит файлы кластера
        parent::deleteAll();
        // кластеры
        $m_dir = $this->getDirClaster();
        $d = new \RecursiveDirectoryIterator($m_dir, \FilesystemIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($d, \RecursiveIteratorIterator::SELF_FIRST);
        $dirs = [];
        // удаляем файлы
        foreach ($it as $dir_or_file) {
            /** @var \SplFileInfo $dir_or_file */
            $pathname = $dir_or_file->getFilename();
            if (\is_dir($pathname)) $dirs[] = $pathname;
            else @\unlink($pathname);
        }
        de($dirs);
        // удаляем директории
        foreach ($dirs as $dir) @\rmdir($dir);
    }
    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    /**
     * main_dir/clasters/[a-z]{36}/[a-z]{2}/[a-z]{36} |
     */
    protected function getPathFileByIDAndName(string $id, string $claster_name): string
    {
        $hash = \md5($id, false);
        return $this->getDirByIDAndName($hash, $claster_name) . self::DIR_SEP . $hash;
    }

    /**
     * main_dir/clasters/[a-z]{36}/[a-z]{2} |
     * отдает только путь до папки где будет хранится файл
     */
    protected function getDirByIDAndName(string $id_hash, string $claster_name): string
    {
        return $this->getDirByName($claster_name) .
            self::DIR_SEP .
            \substr($id_hash, 0, 2);
    }

    /**
     * main_dir/clasters/[a-z]{36} |
     * отдает только путь до папки кластера
     */
    protected function getDirByName(string $claster_name): string
    {
        return $this->getDirClaster() .
            self::DIR_SEP .
            \md5($claster_name, false);
    }

    /**
     * main_dir/clasters |
     */
    protected function getDirClaster(): string
    {
        return $this->cache_dir .
            self::DIR_SEP .
            self::NAME_DIR;
    }
}
