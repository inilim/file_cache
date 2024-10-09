<?php

namespace Inilim\FileCache;

use Closure;

class FileCache
{
   protected int $countRead = 0;
   /**
    * The root cache directory.
    */
   protected string $cacheDir;

   /**
    * Creates a FileCache object
    */
   public function __construct(
      string $cache_dir = '/cache'
   ) {
      $this->cacheDir = $cache_dir;
   }

   public function getCountRead(): int
   {
      return $this->countRead;
   }

   /**
    */
   public function get(string|int|float $id): mixed
   {
      $id        = \strval($id);
      $path_file = $this->getPathFileByID($id);
      return $this->read($path_file);
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

   public function existByID(string|int|float $id): bool
   {
      $id        = \strval($id);
      $path_file = $this->getPathFileByID($id);
      if (!\is_file($path_file)) return false;
      if (@\filemtime($path_file) > \time()) return true;
      return false;
   }

   public function existByIDFromClaster(string|int|float $id, string|int|float $claster_name): bool
   {
      $id           = \strval($id);
      $claster_name = \strval($claster_name);
      $path_file    = $this->getPathFileByIDFromClaster($id, $claster_name);
      if (!\is_file($path_file)) return false;
      if (@\filemtime($path_file) > \time()) return true;
      return false;
   }

   public function deleteByID(string|int|float $id): void
   {
      $id        = \strval($id);
      $path_file = $this->getPathFileByID($id);
      $this->unlink($path_file);
   }

   public function deleteByIDFromClaster(string|int|float $id, string|int|float $claster_name): void
   {
      $id           = \strval($id);
      $claster_name = \strval($claster_name);
      $path_file    = $this->getPathFileByIDFromClaster($id, $claster_name);
      $this->unlink($path_file);
   }

   /**
    * @param boolean $clasters including clustered folders
    */
   public function deleteAll(bool $clasters = false): void
   {
      $files = $this->getAllDir();
      // берем кластерные папки
      if (!$clasters) {
         $files = \array_merge($files, $this->getAllClasterDir());
      }
      $files = \array_map(
         static fn($d) => \glob($d . '/*.cache'),
         $files
      );
      $files = \array_filter(
         $files,
         static fn($item) => \is_array($item)
      );
      $files = \array_merge(...$files);
      \array_map(
         fn(string $file) => $this->unlink($file),
         $files
      );
   }

   /**
    * @param string|int|float $name
    */
   public function deleteAllByNameFromClaster($name): void
   {
      $files = \glob($this->getClasterDirByName(\strval($name)) . '/*');
      if ($files === false) return;
      $files = \array_map(static fn($d) => \glob($d . '/*.cache'), $files);
      $files = \array_filter($files, static fn($item) => \is_array($item));
      $files = \array_merge(...$files);
      \array_map(function (string $file) {
         $this->unlink($file);
      }, $files);
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
      $path_file = $dir . \DIRECTORY_SEPARATOR . \sha1($id, false) . '.cache';
      return $this->saveData($path_file, $data, $lifetime);
   }

   /**
    *
    * @param string|integer|float $id
    * @param string|integer|float $claster_name
    * @param mixed $data
    */
   public function saveToClaster($id, $claster_name, $data, int $lifetime = 3600): bool
   {
      $id           = \strval($id);
      $claster_name = \strval($claster_name);
      $dir          = $this->getClasterDirByIDAndClasterName($id, $claster_name);
      if (!$this->createDir($dir)) return false;
      $path_file = $dir . \DIRECTORY_SEPARATOR . \sha1($id, false) . '.cache';
      return $this->saveData($path_file, $data, $lifetime);
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

   protected function unlink(string $path_to_file): void
   {
      if (!\is_file($path_to_file)) return;
      try {
         @\unlink($path_to_file);
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
      return \implode(\DIRECTORY_SEPARATOR, $dirs);
   }

   /**
    */
   protected function getClasterDirByIDAndClasterName(string $id, string $name): string
   {
      $hash = \sha1($id, false);
      $path = $this->getClasterDirByName($name);
      return $path . \DIRECTORY_SEPARATOR . \substr($hash, 0, 2);
   }

   /**
    */
   protected function getClasterDirByName(string $name): string
   {
      $dirs = [
         $this->getCacheDir(),
         'clasters',
         \sha1(\strval($name), false),
      ];
      return \implode(\DIRECTORY_SEPARATOR, $dirs);
   }

   /**
    * Fetches a base directory to store the cache data
    */
   protected function getCacheDir(): string
   {
      return $this->cacheDir;
   }

   protected function getPathFileByID(string $id): string
   {
      $directory = $this->getDirByID($id);
      $hash      = \sha1($id, false);
      $file      = $directory . \DIRECTORY_SEPARATOR . $hash . '.cache';
      return $file;
   }

   /**
    */
   protected function getPathFileByIDFromClaster(string $id, string $claster_name): string
   {
      $directory = $this->getClasterDirByIDAndClasterName($id, $claster_name);
      $hash      = \sha1($id, false);
      $file      = $directory . \DIRECTORY_SEPARATOR . $hash . '.cache';
      return $file;
   }

   /**
    */
   protected function read(string $path_file): mixed
   {
      if (!\is_file($path_file) || !\is_readable($path_file)) return null;

      if (@\filemtime($path_file) > \time()) {
         $fp = @\fopen($path_file, 'r');
         if ($fp !== false) {
            @\flock($fp, \LOCK_SH);
            $cache_value = @\stream_get_contents($fp);
            if ($cache_value === false) $cache_value = '';
            @\flock($fp, \LOCK_UN);
            @\fclose($fp);
            $this->countRead++;
            return \unserialize($cache_value);
         }
      }
      $this->unlink($path_file);
      return null;
   }

   protected function createDir(string $dir): bool
   {
      if (!\is_dir($dir)) {
         if (@!\mkdir($dir, 0755, true)) return false;
      }
      return true;
   }

   protected function saveData(string $path_file, mixed $data, int $lifetime): bool
   {
      if ($data === null) return false;
      $dir        = \dirname($path_file);
      $serialized = \serialize($data);

      $path_tmp_file = $dir . \DIRECTORY_SEPARATOR . \uniqid('', true);
      $handle = \fopen($path_tmp_file, 'x');
      if ($handle === false) {
         $this->unlink($path_tmp_file);
         return false;
      }
      \fwrite($handle, $serialized);
      \fclose($handle);

      @\touch($path_tmp_file, $lifetime + \time());

      if (\rename($path_tmp_file, $path_file) === false) {
         $this->unlink($path_tmp_file);
         return false;
      }

      return true;
   }

   /**
    * старя реализация
    */
   protected function _saveData(string $path_file, mixed $data, int $lifetime): bool
   {
      if ($data === null) return false;
      $serialized = \serialize($data);
      $result     = @\file_put_contents($path_file, $serialized, \LOCK_EX);
      if ($result === false) return false;
      return @\touch($path_file, $lifetime + \time());
   }

   /**
    * @return array<string>
    */
   protected function getAllDir(): array
   {
      $list_dir = \glob($this->getCacheDir() . '/*');
      if ($list_dir === false) return [];
      $list_dir = \array_filter($list_dir, fn($d) => \strlen(\basename($d)) == 2);
      return $list_dir;
   }

   /**
    * @return array<string>
    */
   protected function getAllClasterDir(): array
   {
      $list_dir = \glob($this->getCacheDir() . '/*');
      if ($list_dir === false || !$list_dir) return [];
      $list_dir = \array_filter($list_dir, fn($d) => \strlen(\basename($d)) > 2);
      if (!$list_dir) return [];
      $list_dir = \array_map(function ($d) {
         return \glob($d . '/*');
      }, $list_dir);
      $list_dir = \array_filter($list_dir, fn($item) => \is_array($item));
      $list_dir = \array_merge(...$list_dir);
      return $list_dir;
   }
}
