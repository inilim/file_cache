<?php

namespace Inilim\FileCache;

use Inilim\FileCache\Cache;
use Psr\SimpleCache\CacheInterface;
use Closure;

class FileNameCache extends Cache implements CacheInterface
{
    // protected const PART = 10;
    protected const PART = 248;
    protected const MAX_COUNT_PART = 5;
    protected const SEP_NAME = '-';
    protected const SEARCH = '/';
    protected const REPLACE = '_';

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) return false;
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) return false;
        }
        return true;
    }

    /**
     * @param mixed $id
     */
    public function has($id): bool
    {
        $dir = $this->getDirByID(\serialize($id));
        if (!\is_dir($dir)) return false;
        $names = $this->getNames($dir);
        if (!$names) return false;
        if (\filemtime($dir) > \time()) return true;
        return false;
    }

    /**
     * @param mixed $id
     */
    public function delete($id): bool
    {
        $dir = $this->getDirByID(\serialize($id));
        if (!\is_dir($dir)) return false;
        $names = $this->getNames($dir);
        if (!$names) {
            $this->removeDir($dir, []);
            return false;
        }
        return $this->removeDir($dir, $names);
    }

    public function clear(): bool
    {
        return $this->deleteFiles($this->cache_dir, true);
    }

    /**
     * @param mixed $id
     * @param mixed $default
     */
    public function get($id, $default = null): mixed
    {
        $dir = $this->getDirByID(\serialize($id));
        if (!\is_dir($dir)) return $default;
        $names = $this->getNamesAsStr($dir);
        if (!$names || \filemtime($dir) < \time()) {
            return $default;
        }
        return $this->read($dir, $names) ?? $default;
    }

    /**
     * @param mixed $id
     * @param mixed  $value
     */
    public function set($id, $value, null|int|\DateInterval $ttl = null): bool
    {
        $dir = $this->getDirByID(\serialize($id));
        // TODO используем file_exists потому что бывает что финальная папка сохраняется как файл, причину такого поведения еще не нашел
        if (\file_exists($dir)) $this->removeDir($dir);
        if (!$this->createDir($dir)) return false;
        $names = $this->createNamesData($value);
        if (!$names) {
            $this->removeDir($dir, []);
            return false;
        }
        return $this->saveData($dir, $names, $this->getLifeTime($ttl));
    }

    /**
     * @param mixed $id
     */
    public function getOrSave($id, Closure $data, int $lifetime = 3600): mixed
    {
        $res = $this->get($id);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->set($id, $res, $lifetime);
        }
        return $res;
    }

    /**
     * @param mixed $value
     */
    public function isCached($value): bool
    {
        if ($value === null) return false;
        if ((\strlen(\base64_encode(\serialize($value))) / self::PART) > self::MAX_COUNT_PART) return false;
        return true;
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    /**
     */
    protected function read(string $dir, string $names): mixed
    {
        $data = \base64_decode(
            \strtr(
                \preg_replace('#([0-9]{1}\-)#', '', $names) ?? '',
                self::REPLACE,
                self::SEARCH,
            ),
            true
        );
        if ($data === false) {
            $this->removeDir($dir);
            return null;
        }
        if ('b:0;' === $data) return false;
        // при неудавшем десириализации выдает false, поэтому делаем проверку выше
        $data = \unserialize($data);
        if ($data === false) return null;
        return $data;
    }

    /**
     * @return string[]|array{}
     */
    protected function getNames(string $dir): array
    {
        // glob очень медленный
        $n = @\scandir($dir);
        if ($n === false) return [];
        // \sort($files, SORT_NATURAL);
        return \array_diff($n, ['.', '..']);
    }

    /**
     */
    protected function getNamesAsStr(string $dir): string
    {
        return \implode('', $this->getNames($dir));
    }

    /**
     * @param mixed $data
     * @return string[]|array{}
     */
    protected function createNamesData($data): array
    {
        if ($data === null) return [];
        $base = \strtr(\base64_encode(\serialize($data)), self::SEARCH, self::REPLACE);
        if ((\strlen($base) / self::PART) > self::MAX_COUNT_PART) {
            throw new \Exception('the length of the data exceeds the limit');
        }
        $i = 0;
        return \array_map(
            function ($part) use (&$i) {
                return ($i++) . self::SEP_NAME . $part;
            },
            \str_split(
                $base,
                self::PART
            )
        );
    }

    /**
     * @param string[] $names
     */
    protected function saveData(string $dir, array $names, int $lifetime): bool
    {
        foreach ($names as $name) {
            // fopen,touch тут медленнее
            if (@\file_put_contents($dir . self::DIR_SEP . $name, '') === false) {
                $this->removeDir($dir, $names);
                return false;
            }
        }
        @\touch($dir, $lifetime + \time());
        return true;
    }

    /**
     * TODO скорость записи такая медленная из-за очищения директории, rmdir не может удалить папку вместе с файлами
     * @param string[]|array{} $names передаем для экономии процессов
     */
    protected function removeDir(string $dir, ?array $names = null): bool
    {
        if (!\file_exists($dir)) return false;
        if (\is_dir($dir)) {
            $names ??= $this->getNames($dir);
            if (!$names) return @\rmdir($dir);
            else {
                \array_map(fn($n) => @\unlink($dir . self::DIR_SEP . $n), $names);
                return @\rmdir($dir);
            }
        } else {
            return @\unlink($dir);
        }
    }

    protected function createDir(string $dir): bool
    {
        if (!\is_dir($dir)) {
            if (@!\mkdir($dir, 0755, true)) return false;
        }
        return true;
    }

    protected function getDirByID(string $id): string
    {
        $hash = \md5($id, false);
        return $this->cache_dir .
            self::DIR_SEP .
            \substr($hash, 0, 2) .
            self::DIR_SEP .
            \substr($hash, 2, 2) .
            self::DIR_SEP .
            $hash;
    }
}
