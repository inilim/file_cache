<?php

namespace Inilim\FileCache;

use Closure;

class FileCache
{
   protected const DIR_SEP = DIRECTORY_SEPARATOR;

   protected int $count_read = 0;
   /**
    * Creates a FileCache object
    */
   public function __construct(
      /**
       * The root cache directory.
       */
      protected string $cache_dir = '/cache'
   ) {
   }

   public function getCountRead(): int
   {
      return $this->count_read;
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
    * существует ли еще файл
    */
   public function existByID(string|int|float $id): bool
   {
      $id        = \strval($id);
      $path_file = $this->getPathFileByID($id);
      if (!\is_file($path_file)) return false;
      if (@\filemtime($path_file) > \time()) return true;
      return false;
   }

   /**
    */
   public function deleteByID(string|int|float $id): void
   {
      $id        = \strval($id);
      $path_file = $this->getPathFileByID($id);
      @\unlink($path_file);
   }

   public function deleteAll(): void
   {
      $list_dir = $this->getAllDir();

      $list_files = \array_map(function ($d) {
         return \glob($d . '/*.cache');
      }, $list_dir);
      $list_files = \array_filter($list_files, fn ($item) => \is_array($item));
      $list_files = \array_merge(...$list_files);
      \array_map(fn ($f) => @\unlink($f), $list_files);
   }

   /**
    * @param mixed  $data
    */
   public function save(string|int|float $id, $data, int $lifetime = 3600): bool
   {
      $id  = \strval($id);
      $dir = $this->getDirByID($id);
      if (!$this->createDir($dir)) return false;
      $path_file = $dir . self::DIR_SEP . \md5($id, false) . '.cache';
      return $this->saveData($path_file, $data, $lifetime);
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

   /**
    * Fetches a directory to store the cache data
    */
   protected function getDirByID(string $id): string
   {
      $hash = \md5($id, false);
      $dirs = [
         $this->getCacheDir(),
         \substr($hash, 0, 2)
      ];
      return \implode(self::DIR_SEP, $dirs);
   }

   /**
    * Fetches a base directory to store the cache data
    */
   protected function getCacheDir(): string
   {
      return $this->cache_dir;
   }

   protected function getPathFileByID(string $id): string
   {
      $directory = $this->getDirByID($id);
      $hash      = \md5($id, false);
      $file      = $directory . self::DIR_SEP . $hash . '.cache';
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
            @\flock($fp, LOCK_SH);
            $cache_value = @\stream_get_contents($fp);
            if ($cache_value === false) $cache_value = '';
            @\flock($fp, LOCK_UN);
            @\fclose($fp);
            $this->count_read++;
            return \unserialize($cache_value);
         }
      }
      @\unlink($path_file);
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

      $path_tmp_file = $dir . self::DIR_SEP . \uniqid(more_entropy: true);
      $handle = \fopen($path_tmp_file, 'x');
      if ($handle === false) {
         @\unlink($path_tmp_file);
         return false;
      }
      \fwrite($handle, $serialized);
      \fclose($handle);

      @\touch($path_tmp_file, $lifetime + \time());

      if (\rename($path_tmp_file, $path_file) === false) {
         @\unlink($path_tmp_file);
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
      $result     = @\file_put_contents($path_file, $serialized, LOCK_EX);
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
      $list_dir = \array_filter($list_dir, fn ($d) => \strlen(\basename($d)) == 2);
      return $list_dir;
   }
}
