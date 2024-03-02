<?php

namespace Inilim\FileCache;

use Closure;
use Inilim\FileCache\FileCache;

class FileCacheClaster extends FileCache
{
    /**
     */
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

    /**
     */
    public function getFromClaster(string|int|float $id, string|int|float $claster_name): mixed
    {
        $id           = \strval($id);
        $claster_name = \strval($claster_name);
        $path_file    = $this->getPathFileByIDFromClaster($id, $claster_name);
        return $this->read($path_file);
    }

    /**
     */
    public function existByIDFromClaster(string|int|float $id, string|int|float $claster_name): bool
    {
        $id           = \strval($id);
        $claster_name = \strval($claster_name);
        $path_file    = $this->getPathFileByIDFromClaster($id, $claster_name);
        if (!\is_file($path_file)) return false;
        if (@\filemtime($path_file) > \time()) return true;
        return false;
    }

    /**
     */
    public function deleteByIDFromClaster(string|int|float $id, string|int|float $claster_name): void
    {
        $id           = \strval($id);
        $claster_name = \strval($claster_name);
        $path_file    = $this->getPathFileByIDFromClaster($id, $claster_name);
        @\unlink($path_file);
    }

    /**
     */
    public function deleteAllByNameFromClaster(string|int|float $name): void
    {
        $list_dir   = \glob($this->getClasterDirByName(\strval($name)) . '/*');
        if ($list_dir === false) return;
        $list_files = \array_map(fn ($d) => \glob($d . '/*.cache'), $list_dir);
        $list_files = \array_filter($list_files, fn ($item) => \is_array($item));
        $list_files = \array_merge(...$list_files);
        \array_map(fn ($f) => @\unlink($f), $list_files);
    }

    /**
     */
    public function saveToClaster(string|int|float $id, string|int|float $claster_name, mixed $data, int $lifetime = 3600): bool
    {
        $id           = \strval($id);
        $claster_name = \strval($claster_name);
        $dir          = $this->getClasterDirByIDAndClasterName($id, $claster_name);
        if (!$this->createDir($dir)) return false;
        $path_file = $dir . DIRECTORY_SEPARATOR . \md5($id, false) . '.cache';
        return $this->saveData($path_file, $data, $lifetime);
    }

    public function deleteAll(bool $clasters = false): void
    {
        $list_dir = $this->getAllDir();
        // берем кластерные папки
        if (!$clasters) {
            $list_dir = \array_merge($list_dir, $this->getAllClasterDir());
        }

        $list_files = \array_map(function ($d) {
            return \glob($d . '/*.cache');
        }, $list_dir);
        $list_files = \array_filter($list_files, fn ($item) => \is_array($item));
        $list_files = \array_merge(...$list_files);
        \array_map(fn ($f) => @\unlink($f), $list_files);
    }

    // ------------------------------------------------------------------
    // ___
    // ------------------------------------------------------------------

    /**
     * @return array<string>
     */
    protected function getAllClasterDir(): array
    {
        $list_dir = \glob($this->getCacheDir() . '/*');
        if ($list_dir === false || !$list_dir) return [];
        $list_dir = \array_filter($list_dir, fn ($d) => \strlen(\basename($d)) > 2);
        if (!$list_dir) return [];
        $list_dir = \array_map(function ($d) {
            return \glob($d . '/*');
        }, $list_dir);
        $list_dir = \array_filter($list_dir, fn ($item) => \is_array($item));
        $list_dir = \array_merge(...$list_dir);
        return $list_dir;
    }

    /**
     */
    protected function getPathFileByIDFromClaster(string $id, string $claster_name): string
    {
        $directory = $this->getClasterDirByIDAndClasterName($id, $claster_name);
        $hash      = \md5($id, false);
        $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
        return $file;
    }

    /**
     */
    protected function getClasterDirByName(string $name): string
    {
        $dirs = [
            $this->getCacheDir(),
            'clasters',
            \md5(\strval($name), false),
        ];
        return \implode(DIRECTORY_SEPARATOR, $dirs);
    }

    /**
     */
    protected function getClasterDirByIDAndClasterName(string $id, string $name): string
    {
        $hash = \md5($id, false);
        $path = $this->getClasterDirByName($name);
        return $path . DIRECTORY_SEPARATOR . \substr($hash, 0, 2);
    }
}