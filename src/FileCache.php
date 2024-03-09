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

   /**
    * @param mixed $id
    */
   public function get($id): mixed
   {
      return $this->read(
         $this->getPathFileByID(\serialize($id))
      );
   }

   /**
    * @param mixed $id
    */
   public function getOrSave($id, Closure $data, int $lifetime = 3600): mixed
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
    * @param mixed $id
    */
   public function exist($id): bool
   {
      $path_file = $this->getPathFileByID(\serialize($id));
      if (!\is_file($path_file)) return false;
      if (\filemtime($path_file) > \time()) return true;
      return false;
   }

   /**
    * @param mixed $id
    */
   public function delete($id): void
   {
      @\unlink(
         $this->getPathFileByID(\serialize($id))
      );
   }

   public function deleteAll(): void
   {
      if (!\is_dir($this->cache_dir)) return;
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($this->cache_dir, \FilesystemIterator::SKIP_DOTS)
      );
      foreach ($it as $file) {
         /** @var \SplFileInfo $file */
         @\unlink($file->getPathname());
      }
   }

   /**
    * @param mixed  $id
    * @param mixed  $data
    */
   public function save($id, $data, int $lifetime = 3600): bool
   {
      if ($data === null) return false;
      $hash = \md5(\serialize($id), false);
      $dir  = $this->getDirByID($hash);
      if (!$this->createDir($dir)) return false;
      $path_file = $dir . self::DIR_SEP . $hash;
      return $this->saveData($path_file, $data, $lifetime);
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

   protected function read(string $path_file): mixed
   {
      if (!\is_file($path_file) || !$h = @\fopen($path_file, 'r')) {
         return null;
      }
      $this->count_read++;
      if (($expires_at = (int) \fgets($h)) && \time() >= $expires_at) {
         \fclose($h);
         @\unlink($path_file);
         return null;
      }

      $data = \stream_get_contents($h);
      if ($data === false) $data = '';
      \fclose($h);

      if ('' === $data) return null;
      if ('b:0;' === $data) return false;
      $data = \unserialize($data);
      if ($data === false) return null;
      return $data;
   }

   /**
    * @param mixed $data
    */
   protected function saveData(string $path_file, $data, int $lifetime): bool
   {
      $expires_at = $lifetime + \time();
      $ser = $expires_at . "\n" . \serialize($data);
      $tmp = \dirname($path_file) . self::DIR_SEP . \uniqid(more_entropy: true);

      try {
         $h = \fopen($tmp, 'x');
      } catch (\Throwable $e) {
         if (!\str_contains($e->getMessage(), 'File exists')) {
            return false;
         }

         $tmp = \dirname($path_file) . self::DIR_SEP . \uniqid(more_entropy: true);
         $h = \fopen($tmp, 'x');
      }

      if ($h === false) {
         @\unlink($tmp);
         return false;
      }
      \fwrite($h, $ser);
      \fclose($h);

      \touch($tmp, $expires_at);

      if (\rename($tmp, $path_file) === false) {
         @\unlink($tmp);
         return false;
      }

      return true;
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
      $hash = \md5($id, false);
      return $this->getDirByID($hash) . self::DIR_SEP . $hash;
   }

   protected function getDirByID(string $id_hash): string
   {
      return $this->cache_dir .
         self::DIR_SEP .
         \substr($id_hash, 0, 2);
   }
}
