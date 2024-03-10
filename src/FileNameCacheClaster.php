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
    public function getOrSaveFromClaster($id, $claster_name, Closure $data, null|int|\DateInterval $ttl = null): mixed
    {
        $res = $this->getFromClaster($id, $claster_name);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->setToClaster($id, $claster_name, $res, $ttl);
        }
        return $res;
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     * @param mixed $default
     */
    public function getFromClaster($id, $claster_name, $default = null): mixed
    {
        $dir = $this->getDirByIDAndName(\serialize($id), \serialize($claster_name));
        if (!\is_dir($dir)) return $default;
        $names = $this->getNamesAsStr($dir);
        if (!$names) {
            return $default;
        }
        return $this->read($dir, $names) ?? $default;
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     * @param mixed  $data
     */
    public function setToClaster($id, $claster_name, mixed $data, null|int|\DateInterval $ttl = null): bool
    {
        $dir = $this->getDirByIDAndName(\serialize($id), \serialize($claster_name));
        if (\file_exists($dir)) $this->removeDir($dir);
        if (!$this->createDir($dir)) return false;
        $names = $this->createNamesData($data);
        if (!$names) {
            $this->removeDir($dir, []);
            return false;
        }
        return $this->saveData($dir, $names, $this->getLifeTime($ttl));
    }

    /**
     * @param mixed $id
     * @param mixed $claster_name
     */
    public function deleteFromClaster($id, $claster_name): bool
    {
        return $this->deleteFiles(
            $this->getDirByIDAndName(\serialize($id), \serialize($claster_name))
        );
    }

    /**
     * @param mixed $claster_name
     */
    public function deleteAllFromClaster($claster_name): bool
    {
        return $this->deleteFiles(
            $this->getDirByName(\serialize($claster_name))
        );
    }

    public function deleteAllClasters(): bool
    {
        return $this->deleteFiles($this->getDirClaster(), true);
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
