<?php

namespace Inilim\FileCache;

use Closure;

class FileNameCache
{
    // protected const PART = 10;
    protected const PART = 248;
    protected const MAX_COUNT_PART = 6;
    protected const SEP_NAME = '-';
    protected const DIR_SEP = DIRECTORY_SEPARATOR;

    public function __construct(
        protected string $cache_dir = '/cache/file_name'
    ) {
    }

    public function existByID(string|int|float $id): bool
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (!\is_dir($dir)) return false;
        $names = $this->getNames($dir);
        if (!$names) return false;
        if (@\filemtime($dir) > \time()) return true;
        return false;
    }

    /**
     */
    public function deleteByID(string|int|float $id): void
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (!\is_dir($dir)) return;
        $names = $this->getNames($dir);
        if (!$names) {
            $this->removeDir($dir, []);
            return;
        }
        $this->removeDir($dir, $names);
    }

    /**
     */
    public function get(string|int|float $id): mixed
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (!\is_dir($dir)) return null;
        $names = $this->getNames($dir);
        if (!$names) {
            $this->removeDir($dir, []);
            return null;
        }
        return $this->read($dir, $names);
    }

    /**
     * @param mixed  $data
     */
    public function save(string|int|float $id, $data, int $lifetime = 3600): bool
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (\is_dir($dir)) $this->removeDir($dir);
        if (!$this->createDir($dir)) return false;
        $names = $this->createNamesData($data);
        if (!$names) {
            $this->removeDir($dir, []);
            return false;
        }
        return $this->saveData($dir, $names, $lifetime);
    }

    /**
     */
    public function getOrSave(string|int|float $id, Closure $data, int $lifetime = 3600): mixed
    {
        $res = $this->get($id);
        if ($res === null) {
            $res = $data() ?? null;
            if ($res === null) return null;
            $this->save($id, $res, $lifetime);
        }
        return $res;
    }

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
     * @param string[] $names
     */
    protected function read(string $dir, array $names): mixed
    {
        if (@\filemtime($dir) < \time()) {
            $this->removeDir($dir, $names);
            return null;
        }

        $data = \array_map(function ($name) {
            return \ltrim(\strstr($name, self::SEP_NAME), self::SEP_NAME);
        }, $names);
        $data = \base64_decode(\implode('', $data), true);
        if ($data === false) {
            $this->removeDir($dir, $names);
            throw new \Exception($dir . ' | base64_decode failed');
        }
        return \unserialize($data);
    }

    /**
     * @return string[]|array{}
     */
    protected function getNames(string $dir): array
    {
        // glob очень медленный
        $files = \scandir($dir);
        if ($files === false) return [];
        // \sort($files, SORT_NATURAL);
        return \array_diff($files, ['.', '..']);
    }

    /**
     * @return string[]|array{}
     */
    // protected function scandir2(string $dir, string $id): array
    // {
    //     $needle = self::SEP_NAME . \md5($id, false) . self::SEP_NAME;
    //     $res = @\opendir($dir);
    //     if (!$res) return [];
    //     $names = [];
    //     while (($name = \readdir($res)) !== false) {
    //         if (\str_contains($name, $needle)) $names[] = $name;
    //     }
    //     \closedir($res);
    //     return $names;
    // }

    /**
     * @param mixed $data
     * @return string[]|array{}
     */
    protected function createNamesData($data): array
    {
        if ($data === null) return [];
        $base = \base64_encode(\serialize($data));
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

    protected function saveData(string $dir, array $names, int $lifetime): bool
    {
        foreach ($names as $name) {
            // fopen тут медленнее
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
        if (!is_dir($dir)) return;
        $names ??= $this->getNames($dir);
        if (!$names) @\rmdir($dir);
        else {
            \array_map(fn ($f) => @\unlink($dir . self::DIR_SEP . $f), $names);
            @\rmdir($dir);
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
        $dirs = [
            $this->getCacheDir(),
            \substr($hash, 0, 2),
            \substr($hash, 2, 2),
            \substr($hash, 4),
        ];
        return \implode(self::DIR_SEP, $dirs);
    }

    protected function getCacheDir(): string
    {
        return $this->cache_dir;
    }
}
