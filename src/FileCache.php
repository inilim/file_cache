<?php

namespace Inilim\FileCache;

use Closure;

class FileCache
{
   protected const DIR_SEP = \DIRECTORY_SEPARATOR;

   protected int $count_read = 0;
   protected readonly string $cache_dir;

   /**
    * Creates a FileCache object
    */
   public function __construct(
      /**
       * The root cache directory.
       */
      string $cache_dir = '/cache'
   ) {
      $this->cache_dir = \rtrim($cache_dir, '/');
   }

   public function getCountRead(): int
   {
      return $this->count_read;
   }

   public function get(string|int|float $id): mixed
   {
      $path_file = $this->getPathFileByID(\strval($id));
      return $this->read($path_file);
   }

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
   public function exist(string|int|float $id): bool
   {
      $path_file = $this->getPathFileByID(\strval($id));
      if (!\is_file($path_file)) return false;
      if (@\filemtime($path_file) > \time()) return true;
      return false;
   }

   public function delete(string|int|float $id): void
   {
      $path_file = $this->getPathFileByID(\strval($id));
      @\unlink($path_file);
   }

   public function deleteAll(): void
   {
      $d = new \RecursiveDirectoryIterator($this->cache_dir, \FilesystemIterator::SKIP_DOTS);
      $it = new \RecursiveIteratorIterator($d);
      foreach ($it as $file) {
         /** @var \SplFileInfo $file */
         @\unlink($file->getPathname());
      }
   }

   /**
    * @param mixed  $data
    */
   public function save(string|int|float $id, $data, int $lifetime = 3600): bool
   {
      $id  = \strval($id);
      $dir = $this->getDirByID($id);
      if (!$this->createDir($dir)) return false;
      $path_file = $dir . self::DIR_SEP . \md5($id, false);
      return $this->saveData($path_file, $data, $lifetime);
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

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
            // ------------------------------------------------------------------
            // ___
            // ------------------------------------------------------------------
            if ('b:0;' === $cache_value) return false;
            $cache_value = \unserialize($cache_value);
            if ($cache_value === false) return null;
            return $cache_value;
         }
      }
      @\unlink($path_file);
      return null;
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
   // protected function _saveData(string $path_file, mixed $data, int $lifetime): bool
   // {
   //    if ($data === null) return false;
   //    $serialized = \serialize($data);
   //    $result     = @\file_put_contents($path_file, $serialized, LOCK_EX);
   //    if ($result === false) return false;
   //    return @\touch($path_file, $lifetime + \time());
   // }

   protected function createDir(string $dir): bool
   {
      if (!\is_dir($dir)) {
         if (@!\mkdir($dir, 0755, true)) return false;
      }
      return true;
   }

   protected function getPathFileByID(string $id): string
   {
      return $this->getDirByID($id) . self::DIR_SEP . \md5($id, false);
   }

   protected function getDirByID(string $id): string
   {
      // $dirs = [
      //    $this->cache_dir,
      //    \substr(\md5($id, false), 0, 2),
      // ];
      // return \implode(self::DIR_SEP, $dirs);

      return $this->cache_dir .
         self::DIR_SEP .
         \substr(\md5($id, false), 0, 2);
   }
}
