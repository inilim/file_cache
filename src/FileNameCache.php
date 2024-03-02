<?php

namespace Inilim\FileCache;

use Closure;

class FileNameCache
{
    // protected const PART = 10;
    protected const PART = 218;
    protected const SEP_NAME = '-';
    protected const DIR_SEP = DIRECTORY_SEPARATOR;

    public function __construct(
        protected string $cache_dir = '/cache/cache_name'
    ) {
    }

    /**
     * @param mixed  $data
     */
    public function save(string|int|float $id, $data, int $lifetime = 3600): bool
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (!$this->createDir($dir)) return false;
        $names = $this->createNamesData($id, $data);
        if (!$names) return false;
        $this->remove($dir, $this->getNames($dir, $id));
        return $this->saveData($dir, $names, $lifetime);
    }

    public function existByID(string|int|float $id): bool
    {
        $id    = \strval($id);
        $dir   = $this->getDirByID($id);
        $names = $this->getNames($dir, $id);
        if (!$names) return false;
        if (@\filemtime($names[0]) > \time()) return true;
        return false;
    }

    /**
     */
    public function deleteByID(string|int|float $id): void
    {
        $id    = \strval($id);
        $dir   = $this->getDirByID($id);
        $names = $this->getNames($dir, $id);
        if (!$names) return;
        $this->remove($dir, $names);
    }

    /**
     */
    public function get(string|int|float $id): mixed
    {
        $id    = \strval($id);
        $dir   = $this->getDirByID($id);
        $names = $this->getNames($dir, $id);
        if (!$names) return null;
        return $this->read($dir, $names);
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

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    /**
     * @param string[] $names
     */
    protected function read(string $dir, array $names): mixed
    {
        if (@\filemtime($dir . self::DIR_SEP . $names[0]) < \time()) {
            $this->remove($dir, $names);
            return null;
        }

        $data = \array_map(function ($name) {
            return \ltrim(\strrchr($name, self::SEP_NAME), self::SEP_NAME);
        }, $names);
        $data = \implode('', $data);

        $data = \base64_decode($data, true);
        if ($data === false) {
            $this->remove($dir, $names);
            throw new \Exception($names[0] . ' | base64_decode failed');
        }
        return \unserialize($data);
    }

    /**
     * @return string[]|array{}
     */
    protected function getNames(string $dir, string $id): array
    {
        $files = $this->scandir($dir, $id);
        \sort($files, SORT_NATURAL);
        return $files;
    }

    /**
     * @return string[]|array{}
     */
    protected function scandir(string $dir, string $id): array
    {
        $needle = self::SEP_NAME . \md5($id, false) . self::SEP_NAME;
        $res = @\opendir($dir);
        if (!$res) return [];
        $names = [];
        while (($name = \readdir($res)) !== false) {
            if (\str_contains($name, $needle)) $names[] = $name;
        }
        \closedir($res);
        return $names;
    }

    /**
     * @param mixed $data
     * @return string[]|array{}
     */
    protected function createNamesData(string $id, $data): array
    {
        if ($data === null) return [];
        $t = self::SEP_NAME . \md5($id, false) . self::SEP_NAME;
        $i = 0;
        return \array_map(
            function ($part) use ($t, &$i) {
                return ($i++) . $t . $part;
            },
            \str_split(
                \base64_encode(\serialize($data)),
                self::PART
            )
        );
    }

    protected function saveData(string $dir, array $names, int $lifetime): bool
    {
        foreach ($names as $name) {
            $p = $dir . self::DIR_SEP . $name;
            if (@\file_put_contents($p, '') === false) {
                @\unlink($p);
                $this->remove($dir, $names);
                return false;
            }
            @\touch($p, $lifetime + \time());
        }
        return true;
    }

    /**
     * @param string[]|array{} $names
     */
    protected function remove(string $dir, array $names): void
    {
        if (!$names) return;
        foreach ($names as $name) @\unlink($dir . self::DIR_SEP . $name);
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
            \substr($hash, 4, 2),
        ];
        return \implode(self::DIR_SEP, $dirs);
    }

    protected function getCacheDir(): string
    {
        return $this->cache_dir;
    }
}
