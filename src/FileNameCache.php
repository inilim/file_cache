<?php

namespace Inilim\FileCache;

use Inilim\FileCache\Cache;
use Closure;

class FileNameCache extends Cache
{
    // protected const PART = 10;
    protected const PART = 248;
    protected const MAX_COUNT_PART = 5;
    protected const SEP_NAME = '-';
    protected const SEARCH = '/';
    protected const REPLACE = '_';

    /**
     * @param mixed $id
     */
    public function existByID($id): bool
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
    public function deleteByID($id): void
    {
        $dir = $this->getDirByID(\serialize($id));
        if (!\is_dir($dir)) return;
        $names = $this->getNames($dir);
        if (!$names) {
            $this->removeDir($dir, []);
            return;
        }
        $this->removeDir($dir, $names);
    }

    /**
     * @param mixed $id
     */
    public function get($id): mixed
    {
        $dir = $this->getDirByID(\serialize($id));
        if (!\is_dir($dir)) return null;
        $names = $this->getNamesAsStr($dir);
        if (!$names || \filemtime($dir) < \time()) {
            return null;
        }
        return $this->read($dir, $names);
    }

    /**
     * @param mixed $id
     * @param mixed  $data
     */
    public function save($id, $data, int $lifetime = 3600): bool
    {
        $dir = $this->getDirByID(\serialize($id));
        // TODO используем file_exists потому что бывает что финальная папка сохраняется как файл, причину такого поведения еще не нашел
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
     */
    public function getOrSave($id, Closure $data, int $lifetime = 3600): mixed
    {
        $res = $this->get($id);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->save($id, $res, $lifetime);
        }
        return $res;
    }

    /**
     * @param mixed $data
     */
    public function isCached($data): bool
    {
        if ($data === null) return false;
        if ((\strlen(\base64_encode(\serialize($data))) / self::PART) > self::MAX_COUNT_PART) return false;
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
        $files = @\scandir($dir);
        if ($files === false) return [];
        // \sort($files, SORT_NATURAL);
        return \array_diff($files, ['.', '..']);
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
        if ((\strlen($base) / self::PART) > self::MAX_COUNT_PART) throw new \Exception('the length of the data exceeds the limit');
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
    protected function removeDir(string $dir, ?array $names = null): void
    {
        if (!\file_exists($dir)) return;
        if (\is_dir($dir)) {
            $names ??= $this->getNames($dir);
            if (!$names) @\rmdir($dir);
            else {
                \array_map(fn ($f) => @\unlink($dir . self::DIR_SEP . $f), $names);
                @\rmdir($dir);
            }
        } else {
            @\unlink($dir);
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
