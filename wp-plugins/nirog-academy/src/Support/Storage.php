<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * Protected storage under wp-content/uploads/nirog-academy/.
 *
 *   media/  encrypted HLS folders. Only .ts segments may be fetched directly;
 *           they are useless without the key. Playlists are served by PHP.
 *   files/  lesson resources (PDF, audio). Never fetched directly.
 *   keys/   HLS decryption keys. Never fetched directly.
 *
 * Hostinger runs LiteSpeed, which honours the .htaccess rules below. For Nginx
 * the equivalent rules are in docs/admin/security-and-backups.md.
 */
final class Storage {
  public static function base_dir(): string {
    $uploads = wp_upload_dir(null, false);
    return trailingslashit($uploads['basedir']) . 'nirog-academy';
  }

  public static function base_url(): string {
    $uploads = wp_upload_dir(null, false);
    return trailingslashit($uploads['baseurl']) . 'nirog-academy';
  }

  public static function dir(string $area): string {
    return self::base_dir() . '/' . $area;
  }

  public static function ensure_directories(): void {
    $deny_all = "# Nirog Bhumi Courses: private files. Served only through the access check.\n"
      . "Options -Indexes\n"
      . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
      . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";

    $media_rules = "# Nirog Bhumi Courses: encrypted video segments only.\n"
      . "Options -Indexes\n"
      . "<FilesMatch \"\\.(?!ts$|m4s$)[^.]+$\">\n"
      . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
      . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
      . "</FilesMatch>\n"
      . "<IfModule mod_headers.c>\n  Header set X-Robots-Tag \"noindex, nofollow\"\n</IfModule>\n";

    $areas = ['' => $deny_all, 'files' => $deny_all, 'keys' => $deny_all, 'media' => $media_rules, 'incoming' => $deny_all];
    foreach ($areas as $area => $rules) {
      $dir = $area === '' ? self::base_dir() : self::dir($area);
      if (!is_dir($dir)) {
        wp_mkdir_p($dir);
      }
      if (is_dir($dir) && is_writable($dir)) {
        file_put_contents($dir . '/.htaccess', $rules);
        if (!file_exists($dir . '/index.php')) {
          file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
      }
    }
  }

  /**
   * Resolve a relative path inside an area and refuse anything that escapes it.
   */
  public static function resolve(string $area, string $relative): ?string {
    $root = realpath(self::dir($area));
    if ($root === false) {
      return null;
    }
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if ($relative === '' || str_contains($relative, '..')) {
      return null;
    }
    $path = realpath($root . '/' . $relative);
    if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
      return null;
    }
    return $path;
  }

  /**
   * Stream a private file with Range support (audio scrubbing and resumable
   * PDF downloads). Exits.
   */
  public static function send_file(string $path, string $download_name, bool $inline): void {
    $size = filesize($path);
    $type = wp_check_filetype($download_name)['type'] ?: 'application/octet-stream';
    $start = 0;
    $end = $size - 1;
    $status = 200;

    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $m)) {
      if ($m[1] !== '') {
        $start = (int) $m[1];
        $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $size - 1;
      } elseif ($m[2] !== '') {
        $start = max(0, $size - (int) $m[2]);
      }
      if ($start > $end || $start >= $size) {
        status_header(416);
        header('Content-Range: bytes */' . $size);
        exit;
      }
      $status = 206;
    }

    while (ob_get_level()) {
      ob_end_clean();
    }
    nocache_headers();
    status_header($status);
    header('Content-Type: ' . $type);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . ($end - $start + 1));
    if ($status === 206) {
      header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($download_name) . '"');

    $fh = fopen($path, 'rb');
    if ($fh === false) {
      exit;
    }
    fseek($fh, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fh) && !connection_aborted()) {
      $chunk = fread($fh, (int) min(65536, $remaining));
      if ($chunk === false) {
        break;
      }
      echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file.
      $remaining -= strlen($chunk);
      flush();
    }
    fclose($fh);
    exit;
  }
}
