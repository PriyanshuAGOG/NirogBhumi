<?php
namespace NirogBhumi\Academy\Media;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Support\Storage;

defined('ABSPATH') || exit;

/**
 * Registers HLS folders uploaded to wp-content/uploads/nirog-academy/media/.
 *
 * For each new folder that holds a master.m3u8:
 *   - moves the encryption key (enc.key) to the private keys/ folder,
 *   - removes ffmpeg's leftover .tmp files,
 *   - reads the total duration from the playlist,
 *   - creates a Video Library entry.
 */
final class Importer {
  /**
   * @return array<int, array{folder: string, status: string, media_id?: int, message?: string}>
   */
  public static function run(): array {
    Storage::ensure_directories();
    $root = Storage::dir('media');
    $results = [];
    if (!is_dir($root)) {
      return $results;
    }
    $known = [];
    foreach (get_posts(['post_type' => PostTypes::MEDIA, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
      $path = (string) get_post_meta($id, '_nba_path', true);
      if ($path !== '') {
        $known[$path] = (int) $id;
      }
    }

    foreach (scandir($root) ?: [] as $entry) {
      if ($entry[0] === '.' || !is_dir($root . '/' . $entry)) {
        continue;
      }
      if (!preg_match('~^[A-Za-z0-9_-]+$~', $entry)) {
        $results[] = ['folder' => $entry, 'status' => 'skipped', 'message' => __('Folder names may only use letters, numbers, - and _.', 'nirog-academy')];
        continue;
      }
      $dir = $root . '/' . $entry;
      if (!is_file($dir . '/master.m3u8')) {
        $results[] = ['folder' => $entry, 'status' => 'skipped', 'message' => __('No master.m3u8 inside.', 'nirog-academy')];
        continue;
      }
      self::cleanup($dir);
      $key_relative = $entry . '.key';
      $key_target = Storage::dir('keys') . '/' . $key_relative;
      foreach (['enc.key', 'key.key', $entry . '.key'] as $candidate) {
        if (is_file($dir . '/' . $candidate)) {
          rename($dir . '/' . $candidate, $key_target);
          break;
        }
      }
      // Any other stray key files must not stay in the public media folder.
      foreach (glob($dir . '/*.key') ?: [] as $stray) {
        unlink($stray);
      }

      if (isset($known[$entry])) {
        if (is_file($key_target)) {
          update_post_meta($known[$entry], '_nba_key_file', $key_relative);
        }
        $results[] = ['folder' => $entry, 'status' => 'exists', 'media_id' => $known[$entry]];
        continue;
      }
      if (!is_file($key_target)) {
        $results[] = ['folder' => $entry, 'status' => 'skipped', 'message' => __('No enc.key found. Was the video encoded with the Academy script?', 'nirog-academy')];
        continue;
      }

      $media_id = wp_insert_post([
        'post_type' => PostTypes::MEDIA,
        'post_status' => 'publish',
        'post_title' => ucwords(str_replace(['-', '_'], ' ', $entry)),
      ]);
      if (is_wp_error($media_id)) {
        $results[] = ['folder' => $entry, 'status' => 'error', 'message' => $media_id->get_error_message()];
        continue;
      }
      update_post_meta($media_id, '_nba_provider', 'hls');
      update_post_meta($media_id, '_nba_path', $entry);
      update_post_meta($media_id, '_nba_key_file', $key_relative);
      update_post_meta($media_id, '_nba_duration_sec', self::duration($dir));
      $results[] = ['folder' => $entry, 'status' => 'imported', 'media_id' => (int) $media_id];
    }
    return $results;
  }

  private static function cleanup(string $dir): void {
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
      /** @var \SplFileInfo $file */
      if ($file->isFile() && (str_ends_with($file->getFilename(), '.tmp') || in_array($file->getExtension(), ['php', 'phtml', 'phar', 'mp4', 'mov'], true))) {
        unlink($file->getPathname());
      }
    }
  }

  /**
   * Total seconds of the first rendition playlist.
   */
  public static function duration(string $dir): int {
    $master = (string) file_get_contents($dir . '/master.m3u8');
    $child = null;
    foreach (preg_split('/\r?\n/', $master) as $line) {
      $line = trim($line);
      if ($line !== '' && $line[0] !== '#') {
        $child = $line;
        break;
      }
    }
    $playlist = $child && !str_contains($child, '..') && is_file($dir . '/' . $child) ? (string) file_get_contents($dir . '/' . $child) : $master;
    preg_match_all('/#EXTINF:([\d.]+)/', $playlist, $m);
    return (int) round(array_sum(array_map('floatval', $m[1] ?? [])));
  }
}
