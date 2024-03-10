<?php

namespace Inilim\FileCache;

use Closure;
use Inilim\FileCache\FileNameCache;

class FileNameCacheClaster extends FileNameCache
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
        $dir = $this->getDirByIDAndName(\serialize($id), \serialize($claster_name));
        if (!\is_dir($dir)) return null;
        $names = $this->getNamesAsStr($dir);
        if (!$names) {
            return null;
        }
        return $this->read($dir, $names);
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     * @param mixed  $data
     */
    public function saveToClaster($id, $claster_name, mixed $data, int $lifetime = 3600): bool
    {
        $dir = $this->getDirByIDAndName(\serialize($id), \serialize($claster_name));
        if (\file_exists($dir)) $this->removeDir($dir);
        if (!$this->createDir($dir)) return false;
        $names = $this->createNamesData($data);
        if (!$names) {
            $this->removeDir($dir, []);
            return false;
        }
        return $this->saveData($dir, $names, $lifetime);
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function deleteFromClaster($id, $claster_name): void
    {
        $this->deleteFiles(
            $this->getDirByIDAndName(\serialize($id), \serialize($claster_name))
        );
    }

    /**
     * @param mixed $claster_name
     */
    public function deleteAllFromClaster($claster_name): void
    {
        $this->deleteFiles(
            $this->getDirByName(\serialize($claster_name))
        );
    }

    public function deleteAllClasters(): void
    {
        $this->deleteFiles($this->getDirClaster(), true);
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    protected function getDirByIDAndName(string $id, string $claster_name): string
    {
        $hash = \md5($id, false);
        return $this->getDirByName($claster_name) .
            self::DIR_SEP .
            \substr($hash, 0, 2) .
            self::DIR_SEP .
            \substr($hash, 2, 2) .
            self::DIR_SEP .
            $hash;
    }

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
