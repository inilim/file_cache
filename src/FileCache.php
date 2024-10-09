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

        $realPath = \realpath($cacheDir);

        if ($realPath === false) {
            throw new \Exception(\sprintf(
                'fail get absolute pathname from "%s"',
                $cacheDir
            ));
        }

        $this->cacheDir = $realPath;
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
        return $this->read(
            $this->getPathFileByID(\strval($id))
        );
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
        $pathToFile   = $this->getPathFileByIDFromClaster(
            \strval($id),
            \strval($clasterName)
        );
        return $this->read($pathToFile);
    }

    /**
     * @param string|int|float $id
     */
    public function existByID($id): bool
    {
        $pathToFile = $this->getPathFileByID(\strval($id));
        if (!\is_file($pathToFile)) return false;
        if (@\filemtime($pathToFile) > \time()) return true;
        return false;
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     */
    public function existByIDFromClaster($id, $clasterName): bool
    {
        $pathToFile = $this->getPathFileByIDFromClaster(
            \strval($id),
            \strval($clasterName)
        );
        if (!\is_file($pathToFile)) return false;
        if (@\filemtime($pathToFile) > \time()) return true;
        return false;
    }

    /**
     * @param string|integer|float $id
     */
    public function deleteByID($id): void
    {
        $pathToFile = $this->getPathFileByID(
            \strval($id)
        );
        $this->unlink($pathToFile);
    }

    /**
     * @param string|int|float $id
     * @param string|int|float $clasterName
     */
    public function deleteByIDFromClaster($id, $clasterName): void
    {
        $pathToFile  = $this->getPathFileByIDFromClaster(
            \strval($id),
            \strval($clasterName)
        );
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
        $this->unlinkAllFromDirs($dirs);
    }

    /**
     * @param string|int|float $name
     */
    public function deleteAllByNameFromClaster($name): void
    {
        $dirs = $this->getFileRecursive(
            $this->getClasterDirByName(\strval($name)),
            [
                new FilterLenFileName(2),
                new FilterOnlyDir,
            ]
        );
        $this->unlinkAllFromDirs($dirs);
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
        $pathToFile = $dir . self::DIR_SEP . \sha1($id, false) . '.' . self::FORMAT;
        return $this->saveData($pathToFile, $data, $lifetime);
    }

    /**
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
        $pathToFile = $dir . self::DIR_SEP . \sha1($id, false) . '.' . self::FORMAT;
        return $this->saveData($pathToFile, $data, $lifetime);
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

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
                \sha1($name, false),
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
        return $directory . self::DIR_SEP . $hash . '.' . self::FORMAT;
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
    protected function saveData(string $pathToFile, $data, int $lifetime): bool
    {
        if ($data === null) return false;
        $dir        = \dirname($pathToFile);
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

        if (\rename($pathToTmpFile, $pathToFile) === false) {
            $this->unlink($pathToTmpFile);
            return false;
        }

        return true;
    }

    protected function unlink(string $pathToFile): void
    {
        if (!\is_file($pathToFile)) return;
        try {
            @\unlink($pathToFile);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param string[] $dirs
     */
    protected function unlinkAllFromDirs(array $dirs): void
    {
        if (!$dirs) return;

        $files = \array_map(
            fn(string $dir) => \glob($dir . '/*.' . self::FORMAT),
            $dirs
        );
        $files = \array_filter(
            $files,
            static fn($globResult) => \is_array($globResult)
        );
        $files = \array_merge(...$files);
        \array_map(
            function (string $pathToFile) {
                $this->unlink($pathToFile);
            },
            $files
        );
    }

    /**
     * @return string[]
     */
    protected function getAllDir(): array
    {
        return $this->getFileRecursive(
            $this->getCacheDir(),
            [
                new FilterLenFileName(2),
                new FilterOnlyDir,
                new FilterRegexByPath(
                    '#[\\\/]{1}' . \preg_quote(self::CLASTER_DIR) . '[\\\/]{1}#',
                    true
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    protected function getAllClasterDir(): array
    {
        return $this->getFileRecursive(
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
     * @return \RecursiveIteratorIterator<\RecursiveDirectoryIterator>|null
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
    protected function getFileRecursive(string $dir, array $filters = []): array
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
