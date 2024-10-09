<?php

declare(strict_types=1);

namespace Inilim\FileCache;

use Inilim\FileCache\FilterOnlyDir;
use Inilim\FileCache\FilterInterface;
use Inilim\FileCache\FilterLenFileName;
use Inilim\FileCache\FilterRegexByPath;

final class FileCache
{
    protected const FORMAT      = 'cache';
    protected const CLASTER_DIR = 'clasters';
    protected const DIR_SEP     = \DIRECTORY_SEPARATOR;

    protected int $countRead = 0;
    /**
     * The root cache directory.
     */
    protected string $cacheDir;

    /**
     * Creates a FileCache object
     */
    public function __construct(
        string $cacheDir = '/cache'
    ) {
        $cacheDir = \rtrim($cacheDir, '/');

        if (!\is_dir($cacheDir)) {
            throw new \Exception(\sprintf(
                'directory "%s" not found',
                $cacheDir
            ));
        }

        $this->cacheDir = $cacheDir;
    }

    public function getCountRead(): int
    {
        return $this->countRead;
    }

    /**
     * Fetches a base directory to store the cache data
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @param string|int|float $id
     * @return mixed
     */
    public function get($id)
    {
        $id        = \strval($id);
        $pathFile = $this->getPathFileByID($id);
        return $this->read($pathFile);
    }

    /**
     * @param string|int|float $id
     * @return mixed
     */
    public function getOrSave($id, \Closure $data, int $lifetime = 3600)
    {
        $res = $this->get($id);
        if ($res === null) {
            $res = $data->__invoke() ?? null;
            if ($res === null) return null;
            $this->save($id, $res, $lifetime);
        }
        return $res;
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     * @return mixed
     */
    public function getOrSaveFromClaster($id, $clasterName, \Closure $data, int $lifetime = 3600)
    {
        $res = $this->getFromClaster($id, $clasterName);
        if ($res === null) {
            $res = $data->__invoke() ?? null;
            if ($res === null) return null;
            $this->saveToClaster($id, $clasterName, $res, $lifetime);
        }
        return $res;
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     * @return mixed
     */
    public function getFromClaster($id, $clasterName)
    {
        $id          = \strval($id);
        $clasterName = \strval($clasterName);
        $pathFile   = $this->getPathFileByIDFromClaster($id, $clasterName);
        return $this->read($pathFile);
    }

    /**
     * @param string|int|float $id
     */
    public function existByID($id): bool
    {
        $id       = \strval($id);
        $pathFile = $this->getPathFileByID($id);
        if (!\is_file($pathFile)) return false;
        if (@\filemtime($pathFile) > \time()) return true;
        return false;
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     */
    public function existByIDFromClaster($id, $clasterName): bool
    {
        $id          = \strval($id);
        $clasterName = \strval($clasterName);
        $pathFile   = $this->getPathFileByIDFromClaster($id, $clasterName);
        if (!\is_file($pathFile)) return false;
        if (@\filemtime($pathFile) > \time()) return true;
        return false;
    }

    /**
     * @param string|integer|float $id
     */
    public function deleteByID($id): void
    {
        $id         = \strval($id);
        $pathToFile = $this->getPathFileByID($id);
        $this->unlink($pathToFile);
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     */
    public function deleteByIDFromClaster($id, $clasterName): void
    {
        $id          = \strval($id);
        $clasterName = \strval($clasterName);
        $pathToFile  = $this->getPathFileByIDFromClaster($id, $clasterName);
        $this->unlink($pathToFile);
    }

    /**
     * @param bool $clasters including clustered folders
     */
    public function deleteAll(bool $clasters = false): void
    {
        $dirs = $this->getAllDir();
        // берем кластерные папки
        if ($clasters) {
            $dirs = \array_merge($dirs, $this->getAllClasterDir());
        }
        $files = \array_map(
            fn($d) => \glob($d . '/*.' . self::FORMAT),
            $dirs
        );
        unset($dirs);
        \array_map(
            fn(string $pathToFile) => $this->unlink($pathToFile),
            \array_merge(...$files)
        );
    }

    /**
     * @param string|int|float $name
     */
    public function deleteAllByNameFromClaster($name): void
    {
        $dirs = $this->getDirRecursive(
            $this->getClasterDirByName(\strval($name)),
            [
                new FilterLenFileName(2),
                new FilterOnlyDir,
            ]
        );

        if (!$dirs) return;
        $files = \array_map(fn($d) => \glob($d . '/*.' . self::FORMAT), $dirs);
        unset($dirs);
        $files = \array_filter($files, static fn($item) => \is_array($item));
        $files = \array_merge(...$files);
        \array_map(
            function (string $pathToFile) {
                $this->unlink($pathToFile);
            },
            $files
        );
    }

    /**
     * @param string|integer|float $id
     * @param mixed $data
     */
    public function save($id, $data, int $lifetime = 3600): bool
    {
        $id  = \strval($id);
        $dir = $this->getDirByID($id);
        if (!$this->createDir($dir)) return false;
        $pathFile = $dir . self::DIR_SEP . \sha1($id, false) . '.' . self::FORMAT;
        return $this->saveData($pathFile, $data, $lifetime);
    }

    /**
     *
     * @param string|integer|float $id
     * @param string|integer|float $clasterName
     * @param mixed $data
     */
    public function saveToClaster($id, $clasterName, $data, int $lifetime = 3600): bool
    {
        $id          = \strval($id);
        $clasterName = \strval($clasterName);
        $dir         = $this->getClasterDirByIDAndClasterName($id, $clasterName);
        if (!$this->createDir($dir)) return false;
        $pathFile = $dir . self::DIR_SEP . \sha1($id, false) . '.' . self::FORMAT;
        return $this->saveData($pathFile, $data, $lifetime);
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    protected function unlink(string $pathToFile): void
    {
        if (!\is_file($pathToFile)) return;
        try {
            @\unlink($pathToFile);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Fetches a directory to store the cache data
     */
    protected function getDirByID(string $id): string
    {
        $hash = \sha1($id, false);
        $dirs = [
            $this->getCacheDir(),
            \substr($hash, 0, 2)
        ];
        return \implode(self::DIR_SEP, $dirs);
    }

    protected function getClasterDirByIDAndClasterName(string $id, string $name): string
    {
        $hash = \sha1($id, false);
        $path = $this->getClasterDirByName($name);
        return $path . self::DIR_SEP . \substr($hash, 0, 2);
    }

    protected function getClasterDir(): string
    {
        return \implode(
            self::DIR_SEP,
            [
                $this->getCacheDir(),
                self::CLASTER_DIR,
            ]
        );
    }

    protected function getClasterDirByName(string $name): string
    {
        return \implode(
            self::DIR_SEP,
            [
                $this->getClasterDir(),
                \sha1(\strval($name), false),
            ]
        );
    }

    protected function getPathFileByID(string $id): string
    {
        $directory = $this->getDirByID($id);
        $hash      = \sha1($id, false);
        $file      = $directory . self::DIR_SEP . $hash . '.' . self::FORMAT;
        return $file;
    }

    protected function getPathFileByIDFromClaster(string $id, string $clasterName): string
    {
        $directory = $this->getClasterDirByIDAndClasterName($id, $clasterName);
        $hash      = \sha1($id, false);
        $file      = $directory . self::DIR_SEP . $hash . '.' . self::FORMAT;
        return $file;
    }

    /**
     * @return mixed
     */
    protected function read(string $pathToFile)
    {
        if (!\is_file($pathToFile) || !\is_readable($pathToFile)) return null;

        if (@\filemtime($pathToFile) > \time()) {
            $fp = @\fopen($pathToFile, 'r');
            if ($fp !== false) {
                @\flock($fp, \LOCK_SH);
                $cacheValue = @\stream_get_contents($fp);
                if ($cacheValue === false) $cacheValue = '';
                @\flock($fp, \LOCK_UN);
                @\fclose($fp);
                $this->countRead++;
                return \unserialize($cacheValue);
            }
        }
        $this->unlink($pathToFile);
        return null;
    }

    protected function createDir(string $dir): bool
    {
        if (!\is_dir($dir)) {
            if (@!\mkdir($dir, 0755, true)) return false;
        }
        return true;
    }

    /**
     * @param mixed $data
     */
    protected function saveData(string $pathFile, $data, int $lifetime): bool
    {
        if ($data === null) return false;
        $dir        = \dirname($pathFile);
        $serialized = \serialize($data);

        $pathToTmpFile = $dir . self::DIR_SEP . \uniqid('', true);
        $handle      = \fopen($pathToTmpFile, 'x');
        if ($handle === false) {
            $this->unlink($pathToTmpFile);
            return false;
        }
        \fwrite($handle, $serialized);
        \fclose($handle);

        @\touch($pathToTmpFile, $lifetime + \time());

        if (\rename($pathToTmpFile, $pathFile) === false) {
            $this->unlink($pathToTmpFile);
            return false;
        }

        return true;
    }

    /**
     * старя реализация
     */
    protected function _saveData(string $pathFile, $data, int $lifetime): bool
    {
        if ($data === null) return false;
        $serialized = \serialize($data);
        $result     = @\file_put_contents($pathFile, $serialized, \LOCK_EX);
        if ($result === false) return false;
        return @\touch($pathFile, $lifetime + \time());
    }

    /**
     * @return string[]
     */
    protected function getAllDir(): array
    {
        return $this->getDirRecursive(
            $this->getCacheDir(),
            [
                new FilterLenFileName(2),
                new FilterOnlyDir,
                new FilterRegexByPath('#[\\\/]{1}' . \preg_quote(self::CLASTER_DIR) . '[\\\/]{1}#'),
            ]
        );
    }

    /**
     * @return string[]
     */
    protected function getAllClasterDir(): array
    {
        return $this->getDirRecursive(
            $this->getClasterDir(),
            [
                new FilterLenFileName(2),
                new FilterOnlyDir,
            ]
        );
    }

    // ------------------------------------------------------------------
    // 
    // ------------------------------------------------------------------

    /**
     * @return \RecursiveIteratorIterator<\SplFileInfo>|null
     */
    protected function getIteratorByDir(string $dir): ?\RecursiveIteratorIterator
    {
        if (!\is_dir($dir)) {
            return null;
        }

        $directoryIterator = new \RecursiveDirectoryIterator(
            $dir,
            \FilesystemIterator::SKIP_DOTS
        );

        $iteratorIterator = new \RecursiveIteratorIterator(
            $directoryIterator,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        return $iteratorIterator;
    }

    /**
     * @param FilterInterface[] $filters
     * @return string[]
     */
    protected function getDirRecursive(string $dir, array $filters = []): array
    {
        $iteratorIterator = $this->getIteratorByDir($dir);
        if ($iteratorIterator === null) return [];

        $dirs = [];
        foreach ($iteratorIterator as $splFileInfo) {
            /** @var \SplFileInfo $splFileInfo */
            if (!$this->filter($splFileInfo, $filters)) {
                continue;
            }
            $dirs[] = $splFileInfo->getPathname();
        }
        return $dirs;
    }

    /**
     * @param FilterInterface[] $filters
     */
    protected function filter(\SplFileInfo $splFileInfo, array $filters = []): bool
    {
        foreach ($filters as $filter) {
            if (!$filter->__invoke($splFileInfo)) {
                return false;
            }
        }

        return true;
    }
}
