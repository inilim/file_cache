<?php

namespace Inilim;

use Closure;

class FileCache
{
   private int $count_read = 0;
   /**
    * Creates a FileCache object
    */
   public function __construct(
      /**
       * The root cache directory.
       */
      private string $cache_dir = '/cache'
   ) {
   }

   public function getCountRead(): int
   {
      return $this->count_read;
   }

   /**
    * Fetches an entry from the cache.
    *
    * @param string|int|float $id
    */
   public function get($id): mixed
   {
      $id = strval($id);
      $path_file = $this->getPathFileByID($id);
      return $this->read($path_file);
   }

   /**
    * Fetches an entry from the cache.
    *
    * @param string|int|float $id
    */
   public function getOrSave($id, Closure $data, int $lifetime = 3600): mixed
   {
      $res = $this->get($id);
      if (is_null($res)) {
         $res = $data() ?? null;
         if (is_null($res)) return null;
         $this->save($id, $res, $lifetime);
      }
      return $res;
   }

   /**
    * @param string|int|float $id
    * @param string|int|float $claster_name
    */
   public function getOrSaveFromClaster($id, $claster_name, Closure $data, int $lifetime = 3600): mixed
   {
      $res = $this->getFromClaster($id, $claster_name);
      if (is_null($res)) {
         $res = $data() ?? null;
         if (is_null($res)) return null;
         $this->saveToClaster($id, $claster_name, $res, $lifetime);
      }
      return $res;
   }


   /**
    * @param string|int|float $id
    * @param string|int|float $claster_name
    */
   public function getFromClaster($id, $claster_name): mixed
   {
      $id = strval($id);
      $claster_name = strval($claster_name);
      $path_file = $this->getPathFileByIDFromClaster($id, $claster_name);
      return $this->read($path_file);
   }

   /**
    * существует ли еще файл
    * @param string|int|float $id
    */
   public function existByID($id): bool
   {
      $id = strval($id);
      $path_file = $this->getPathFileByID($id);
      if (!is_file($path_file)) return false;
      if (@filemtime($path_file) > time()) return true;
      return false;
   }

   /**
    * @param string|int|float $id
    * @param string|int|float $claster_name
    * @return boolean
    */
   public function existByIDFromClaster($id, $claster_name): bool
   {
      $id = strval($id);
      $claster_name = strval($claster_name);
      $path_file = $this->getPathFileByIDFromClaster($id, $claster_name);
      if (!is_file($path_file)) return false;
      if (@filemtime($path_file) > time()) return true;
      return false;
   }

   /**
    * Deletes a cache entry.
    *
    * @param string|int|float $id
    */
   public function deleteByID($id): void
   {
      $id = strval($id);
      $path_file = $this->getPathFileByID($id);
      @unlink($path_file);
   }

   /**
    * @param string|int|float $id
    * @param string|int|float $claster_name
    */
   public function deleteByIDFromClaster($id, $claster_name): void
   {
      $id = strval($id);
      $claster_name = strval($claster_name);
      $path_file = $this->getPathFileByIDFromClaster($id, $claster_name);
      @unlink($path_file);
   }

   public function deleteAll(bool $clasters = false): void
   {
      $list_dir = $this->getAllDir();
      // берем кластерные папки
      if (!$clasters) {
         $list_dir = array_merge($list_dir, $this->getAllClasterDir());
      }

      $list_files = array_map(function ($d) {
         return glob($d . '/*.cache');
      }, $list_dir);
      $list_files = array_filter($list_files, fn ($item) => is_array($item));
      $list_files = array_merge(...$list_files);
      array_map(fn ($f) => @unlink($f), $list_files);
   }

   /**
    * @param string|int|float $name
    */
   public function deleteAllByNameFromClaster($name): void
   {
      $list_dir = glob($this->getClasterDirByName(strval($name)) . '/*');
      if ($list_dir === false) return;
      $list_files = array_map(fn ($d) => glob($d . '/*.cache'), $list_dir);
      $list_files = array_filter($list_files, fn ($item) => is_array($item));
      $list_files = array_merge(...$list_files);
      array_map(fn ($f) => @unlink($f), $list_files);
   }

   /**
    * Puts data into the cache.
    *
    * @param string|int|float $id
    * @param mixed  $data
    */
   public function save($id, $data, int $lifetime = 3600): bool
   {
      $id = strval($id);
      $dir = $this->getDirByID($id);
      if (!$this->createDir($dir)) return false;
      $path_file  = $this->getPathFileByID($id);
      return $this->saveData($path_file, $data, $lifetime);
   }

   /**
    * Puts data into the cache.
    *
    * @param string|int|float $id
    * @param string|int|float $claster_name
    */
   public function saveToClaster($id, $claster_name, mixed $data, int $lifetime = 3600): bool
   {
      $id = strval($id);
      $claster_name = strval($claster_name);
      $dir = $this->getClasterDirByIDAndClasterName($id, $claster_name);
      if (!$this->createDir($dir)) return false;
      $path_file  = $this->getPathFileByIDFromClaster($id, $claster_name);
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
      $hash = sha1($id, false);
      $dirs = [
         $this->getCacheDir(),
         substr($hash, 0, 2)
      ];
      return implode(DIRECTORY_SEPARATOR, $dirs);
   }

   /**
    */
   protected function getClasterDirByIDAndClasterName(string $id, string $name): string
   {
      $hash = sha1($id, false);
      $path = $this->getClasterDirByName($name);
      return $path . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
   }

   /**
    */
   protected function getClasterDirByName(string $name): string
   {
      $dirs = [
         $this->getCacheDir(),
         'clasters',
         sha1(strval($name), false),
      ];
      return implode(DIRECTORY_SEPARATOR, $dirs);
   }

   /**
    * Fetches a base directory to store the cache data
    */
   protected function getCacheDir(): string
   {
      return $this->cache_dir;
   }

   /**
    * Fetches a file path of the cache data
    */
   protected function getPathFileByID(string $id): string
   {
      $directory = $this->getDirByID($id);
      $hash      = sha1($id, false);
      $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
      return $file;
   }

   /**
    */
   protected function getPathFileByIDFromClaster(string $id, string $claster_name): string
   {
      $directory = $this->getClasterDirByIDAndClasterName($id, $claster_name);
      $hash      = sha1($id, false);
      $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
      return $file;
   }

   protected function counterRead(): void
   {
      $this->count_read++;
   }

   /**
    */
   protected function read(string $path_file): mixed
   {
      if (!is_file($path_file) || !is_readable($path_file)) return null;

      if (@filemtime($path_file) > time()) {
         $fp = @fopen($path_file, 'r');
         if ($fp !== false) {
            @flock($fp, LOCK_SH);
            $cache_value = @stream_get_contents($fp);
            if ($cache_value === false) $cache_value = '';
            @flock($fp, LOCK_UN);
            @fclose($fp);
            $this->counterRead();
            return unserialize($cache_value);
         }
      }
      @unlink($path_file);
      return null;
   }

   protected function createDir(string $dir): bool
   {
      if (!is_dir($dir)) {
         if (@!mkdir($dir, 0755, true)) return false;
      }
      return true;
   }

   protected function saveData(string $path_file, mixed $data, int $lifetime): bool
   {
      if (is_null($data)) return false;
      $serialized = serialize($data);
      $result     = @file_put_contents($path_file, $serialized, LOCK_EX);
      if ($result === false) return false;
      return @touch($path_file, $lifetime + time());
   }

   /**
    * @return array<string>
    */
   protected function getAllDir(): array
   {
      $list_dir = glob($this->getCacheDir() . '/*');
      if ($list_dir === false) return [];
      $list_dir = array_filter($list_dir, fn ($d) => strlen(basename($d)) == 2);
      return $list_dir;
   }

   /**
    * @return array<string>
    */
   protected function getAllClasterDir(): array
   {
      $list_dir = glob($this->getCacheDir() . '/*');
      if ($list_dir === false || !sizeof($list_dir)) return [];
      $list_dir = array_filter($list_dir, fn ($d) => strlen(basename($d)) > 2);
      if (!sizeof($list_dir)) return [];
      $list_dir = array_map(function ($d) {
         return glob($d . '/*');
      }, $list_dir);
      $list_dir = array_filter($list_dir, fn ($item) => is_array($item));
      $list_dir = array_merge(...$list_dir);
      return $list_dir;
   }
}
