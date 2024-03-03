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
      $m_dir = $this->getCacheDir();
      $names = $this->getAllNamesFromDir($m_dir);
      if (!$names) return;

      \array_map(function ($n) use ($m_dir) {
         // 
         $dir2 = $m_dir . self::DIR_SEP . $n;
         // 
         if (\is_file($dir2)) {
            @\unlink($dir2);
            return;
         }
         // 
         \array_map(function ($nn) use ($dir2) {
            // 
            $dir3 = $dir2 . self::DIR_SEP . $nn;
            // 
            if (\is_file($dir3)) @\unlink($dir3);
            // 
         }, $this->getAllNamesFromDir($dir2));
         // 
      }, $names);
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
            return \unserialize($cache_value);
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

   /**
    * возвращает абсолютные пути
    * @return string[]|array{}
    */
   protected function getAllNamesFromDir(string $dir): array
   {
      $names = @\scandir($dir);
      if (!$names) return [];
      $names = \array_diff($names, ['.', '..']);
      return \array_map(fn ($n) => $n, $names);
   }

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
      $dirs = [
         $this->getCacheDir(),
         \substr(\md5($id, false), 0, 2),
      ];
      return \implode(self::DIR_SEP, $dirs);
   }

   protected function getCacheDir(): string
   {
      return $this->cache_dir;
   }
}
