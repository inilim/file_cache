<?php

namespace Inilim\FileCache;

use Inilim\FileCache\Cache;
use Psr\SimpleCache\CacheInterface;
use Closure;

class FileCache extends Cache implements CacheInterface
{
   public function getCountRead(): int
   {
      return $this->count_read;
   }

   public function getMultiple(iterable $keys, mixed $default = null): iterable
   {
      $values = [];
      foreach ($keys as $key) {
         $values[$key] = $this->get($key, $default);
      }
      return $values;
   }

   public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
   {
      foreach ($values as $key => $value) {
         if (!$this->set($key, $value, $ttl)) return false;
      }
      return true;
   }

   public function deleteMultiple(iterable $keys): bool
   {
      foreach ($keys as $key) {
         if (!$this->delete($key)) return false;
      }
      return true;
   }


   /**
    * @param mixed $id
    * @param mixed $default
    */
   public function get($id, $default = null): mixed
   {
      return $this->read(
         $this->getPathFileByID(\serialize($id))
      ) ?? $default;
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
         $this->set($id, $res, $lifetime);
      }
      return $res;
   }

   /**
    * @param mixed $id
    */
   public function has($id): bool
   {
      $path_file = $this->getPathFileByID(\serialize($id));
      if (!\is_file($path_file)) return false;
      if (\filemtime($path_file) > \time()) return true;
      return false;
   }

   /**
    * @param mixed $id
    */
   public function delete($id): bool
   {
      return $this->deleteFiles($this->getPathFileByID(\serialize($id)));
   }

   public function clear(): bool
   {
      return $this->deleteFiles($this->cache_dir, true);
   }

   /**
    * @param mixed  $id
    * @param mixed  $value
    */
   public function set($id, $value, null|int|\DateInterval $ttl = null): bool
   {
      if ($value === null) return false;
      $hash = \md5(\serialize($id), false);
      $dir  = $this->getDirByID($hash);
      if (!$this->createDir($dir)) return false;
      $path_file = $dir . self::DIR_SEP . $hash;

      return $this->saveData($path_file, $value, $this->getLifeTime($ttl));
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
    * @param mixed $value
    */
   protected function saveData(string $path_file, $value, int $lifetime): bool
   {
      $expires_at = $lifetime + \time();
      $ser = $expires_at . "\n" . \serialize($value);
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
