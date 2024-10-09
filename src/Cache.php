<?php

namespace Inilim\FileCache;

abstract class Cache
{
    protected const DIR_SEP = \DIRECTORY_SEPARATOR;
    protected string $cache_dir;
    protected int $count_read = 0;
    protected int $default_ttl = 3600;

    public function __construct(
        string $cacheDir
    ) {
        $this->cache_dir = \rtrim($cacheDir, '/');
    }

    /**
     * удаляет файл или директорию рекурсивно
     */
    protected function deleteFiles(string $file, bool $save_main_dir = false): bool
    {
        if (!\file_exists($file)) return false;
        if (\is_file($file)) {
            return @\unlink($file);
        } else {
            $this->deleteAllFromDir($file, $save_main_dir);
        }
        return true;
    }

    protected function getLifeTime(null|int|\DateInterval $ttl): int
    {
        if (\is_int($ttl)) return $ttl;
        elseif ($ttl === null) return $this->default_ttl;
        else {
            return (new \DateTime)->add($ttl)->getTimestamp() - \time();
        }
    }

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

    // ------------------------------------------------------------------
    // ____
    // ------------------------------------------------------------------

    /**
     * на рефакторинг
     */
    private function deleteAllFromDir(string $m_dir, bool $save_main_dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($m_dir, \FilesystemIterator::SKIP_DOTS),
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
        // удаляем главную директорию
        if (!$save_main_dir) $dirs[] = $m_dir;
        // INFO сортируем от дочерних к родителям
        \usort($dirs, static function ($a, $b) {
            $la = \strlen($a);
            $lb = \strlen($b);
            if ($la < $lb) return 1;
            elseif ($la == $lb) return 0;
            return -1;
        });
        // удаляем директории
        foreach ($dirs as $dir) @\rmdir($dir);
    }
}
