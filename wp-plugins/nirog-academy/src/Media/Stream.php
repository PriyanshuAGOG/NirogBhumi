<?php
namespace NirogBhumi\Academy\Media;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Support\Crypto;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Storage;

defined('ABSPATH') || exit;

/**
 * Protected media delivery.
 *
 * Self-hosted video is AES-128 encrypted HLS. Encrypted segments are plain
 * static files (cheap for the server to send). The playlists and the key are
 * sent by PHP, only after the access check, through short-lived signed URLs
 * tied to the user.
 *
 *   /?nba_stream=playlist  master or rendition playlist (rewritten)
 *   /?nba_stream=key       decryption key
 *   /?nba_stream=file      private audio / MP4 file (Range supported)
 *   /?nba_stream=res       lesson download (PDF etc.)
 *   /?nba_stream=booklet   booklet PDF for the in-page reader
 */
final class Stream {
  public function register(): void {
    add_action('parse_request', [$this, 'handle'], 1);
  }

  // ------------------------------------------------------------ signing

  private function url(string $kind, array $params): string {
    $user_id = get_current_user_id();
    $expires = time() + nba()->settings->int('signed_url_ttl');
    $params = array_merge(['nba_stream' => $kind], $params, ['u' => $user_id, 'e' => $expires]);
    $params['s'] = Crypto::sign($this->sign_parts($params));
    return add_query_arg(array_map('rawurlencode', array_map('strval', $params)), home_url('/'));
  }

  private function sign_parts(array $p): array {
    return [$p['nba_stream'] ?? '', $p['m'] ?? '', $p['l'] ?? '', $p['r'] ?? '', $p['a'] ?? '', $p['u'] ?? '', $p['e'] ?? ''];
  }

  // ------------------------------------------------------------ player config

  public function media_for_lesson(int $lesson_id): int {
    if (I18n::current() === 'hi_IN') {
      $hi = (int) get_post_meta($lesson_id, '_nba_media_id_hi', true);
      if ($hi && get_post_type($hi) === PostTypes::MEDIA) {
        return $hi;
      }
    }
    $id = (int) get_post_meta($lesson_id, '_nba_media_id', true);
    return $id && get_post_type($id) === PostTypes::MEDIA ? $id : 0;
  }

  public function provider(int $media_id): string {
    return (string) get_post_meta($media_id, '_nba_provider', true) ?: 'hls';
  }

  /**
   * Whether playback can be measured (YouTube embeds cannot, without loading
   * YouTube's tracking script).
   */
  public function trackable(int $media_id): bool {
    return $media_id > 0 && $this->provider($media_id) !== 'youtube';
  }

  /**
   * Everything the lesson player needs. Call only after the access check.
   *
   * @return array<string, mixed>|null
   */
  public function player_config(int $lesson_id): ?array {
    $media_id = $this->media_for_lesson($lesson_id);
    if (!$media_id) {
      return null;
    }
    $type = (string) get_post_meta($lesson_id, '_nba_type', true) === 'audio' ? 'audio' : 'video';
    $provider = $this->provider($media_id);
    $base = [
      'media' => $media_id,
      'lesson' => $lesson_id,
      'type' => $type,
      'duration' => (int) get_post_meta($media_id, '_nba_duration_sec', true),
      'trackable' => $this->trackable($media_id),
    ];
    switch ($provider) {
      case 'hls':
        return $base + ['kind' => 'hls', 'src' => $this->url('playlist', ['m' => $media_id, 'l' => $lesson_id, 'r' => 'master.m3u8'])];
      case 'file':
        $file = (string) get_post_meta($media_id, '_nba_file', true);
        if (preg_match('/\.(mp3|m4a|aac|ogg|wav)$/i', $file)) {
          $base['type'] = 'audio';
        }
        return $base + ['kind' => 'file', 'src' => $this->url('file', ['m' => $media_id, 'l' => $lesson_id])];
      case 'youtube':
        $yt = (string) get_post_meta($media_id, '_nba_youtube_id', true);
        return $yt ? $base + ['kind' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($yt) . '?rel=0&modestbranding=1&playsinline=1'] : null;
      case 'external':
        $url = (string) get_post_meta($media_id, '_nba_external_url', true);
        if (!$url) {
          return null;
        }
        return $base + ['kind' => str_contains(strtolower((string) wp_parse_url($url, PHP_URL_PATH)), '.m3u8') ? 'hls' : 'file', 'src' => $url];
    }
    return null;
  }

  /**
   * Signed link to a lesson's booklet PDF, for the in-page reader.
   */
  public function booklet_url(int $lesson_id): string {
    return $this->url('booklet', ['l' => $lesson_id]);
  }

  /** @return array{path: string, name: string, size: int}|null */
  public function booklet_file(int $lesson_id): ?array {
    $relative = (string) get_post_meta($lesson_id, '_nba_file', true);
    $path = $relative ? Storage::resolve('files', $relative) : null;
    if (!$path || !is_file($path)) {
      return null;
    }
    return ['path' => $path, 'name' => (string) get_post_meta($lesson_id, '_nba_file_name', true) ?: wp_basename($path), 'size' => (int) filesize($path)];
  }

  public function resource_url(int $lesson_id, int $attachment_id): string {
    return $this->url('res', ['l' => $lesson_id, 'a' => $attachment_id]);
  }

  // ------------------------------------------------------------ request handling

  public function handle(\WP $wp): void {
    // phpcs:disable WordPress.Security.NonceVerification -- signed URLs replace nonces here.
    if (empty($_GET['nba_stream'])) {
      return;
    }
    $p = [];
    foreach (['nba_stream', 'm', 'l', 'r', 'a', 'u', 'e', 's'] as $k) {
      $p[$k] = isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : '';
    }
    // phpcs:enable
    $kind = $p['nba_stream'];
    $user_id = get_current_user_id();

    if (!in_array($kind, ['playlist', 'key', 'file', 'res', 'booklet'], true)) {
      $this->fail(404);
    }
    if ((int) $p['e'] < time()) {
      $this->fail(410, 'expired');
    }
    if ((int) $p['u'] !== $user_id || !Crypto::verify($this->sign_parts($p), $p['s'])) {
      $this->fail(403, 'signature');
    }
    $lesson_id = (int) $p['l'];
    $access = nba()->gate->check_lesson($user_id, $lesson_id, false);
    if (!$access->allowed) {
      $this->fail(403, $access->code);
    }

    if ($kind === 'res') {
      $this->send_resource($lesson_id, (int) $p['a']);
    }
    if ($kind === 'booklet') {
      $file = $this->booklet_file($lesson_id);
      if (!$file) {
        $this->fail(404);
      }
      Storage::send_file($file['path'], $file['name'], true);
    }

    $media_id = (int) $p['m'];
    $allowed_media = [(int) get_post_meta($lesson_id, '_nba_media_id', true), (int) get_post_meta($lesson_id, '_nba_media_id_hi', true)];
    if (!$media_id || !in_array($media_id, $allowed_media, true)) {
      $this->fail(404);
    }

    match ($kind) {
      'playlist' => $this->send_playlist($media_id, $lesson_id, $p['r']),
      'key' => $this->send_key($media_id),
      'file' => $this->send_media_file($media_id),
    };
  }

  private function fail(int $status, string $reason = ''): never {
    nocache_headers();
    status_header($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-NBA-Reason: ' . preg_replace('/[^a-z_]/', '', $reason));
    echo esc_html($status === 410 ? 'Link expired. Reload the page.' : 'Not available.');
    exit;
  }

  private function hls_dir(int $media_id): ?string {
    $folder = (string) get_post_meta($media_id, '_nba_path', true);
    if ($folder === '') {
      return null;
    }
    $dir = Storage::resolve('media', $folder);
    return $dir && is_dir($dir) ? $dir : null;
  }

  private function send_playlist(int $media_id, int $lesson_id, string $relative): never {
    $dir = $this->hls_dir($media_id);
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if (!$dir || !preg_match('~^[A-Za-z0-9_./-]+\.m3u8$~', $relative) || str_contains($relative, '..')) {
      $this->fail(404);
    }
    $path = realpath($dir . '/' . $relative);
    if (!$path || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
      $this->fail(404);
    }
    $sub = trim(dirname($relative), './');
    $folder = trim((string) get_post_meta($media_id, '_nba_path', true), '/');
    $segment_base = Storage::base_url() . '/media/' . implode('/', array_map('rawurlencode', explode('/', $folder))) . ($sub !== '' ? '/' . implode('/', array_map('rawurlencode', explode('/', $sub))) : '') . '/';

    $out = [];
    foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) as $line) {
      $trim = trim($line);
      if ($trim === '') {
        $out[] = '';
        continue;
      }
      if (str_starts_with($trim, '#EXT-X-KEY') || str_starts_with($trim, '#EXT-X-SESSION-KEY')) {
        $key_url = $this->url('key', ['m' => $media_id, 'l' => $lesson_id]);
        $out[] = preg_replace('/URI="[^"]*"/', 'URI="' . $key_url . '"', $trim);
        continue;
      }
      if (str_starts_with($trim, '#')) {
        $out[] = $trim;
        continue;
      }
      if (str_ends_with(strtolower(strtok($trim, '?')), '.m3u8')) {
        // A rendition playlist referenced by the master playlist.
        $child = ($sub !== '' ? $sub . '/' : '') . $trim;
        $out[] = $this->url('playlist', ['m' => $media_id, 'l' => $lesson_id, 'r' => $child]);
        continue;
      }
      // A segment: served directly as a static (encrypted) file.
      if (!preg_match('~^[A-Za-z0-9_./-]+$~', $trim) || str_contains($trim, '..')) {
        continue;
      }
      $out[] = $segment_base . $trim;
    }

    while (ob_get_level()) {
      ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: application/vnd.apple.mpegurl');
    header('X-Content-Type-Options: nosniff');
    echo implode("\n", $out); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- playlist text built from validated lines.
    exit;
  }

  private function send_key(int $media_id): never {
    $relative = (string) get_post_meta($media_id, '_nba_key_file', true);
    $path = $relative ? Storage::resolve('keys', $relative) : null;
    if (!$path || !is_file($path)) {
      $this->fail(404);
    }
    $key = (string) file_get_contents($path);
    if (strlen($key) !== 16) {
      $this->fail(500);
    }
    while (ob_get_level()) {
      ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Length: 16');
    header('X-Content-Type-Options: nosniff');
    echo $key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary key.
    exit;
  }

  private function send_media_file(int $media_id): never {
    $relative = (string) get_post_meta($media_id, '_nba_file', true);
    $path = $relative ? Storage::resolve('files', $relative) : null;
    if (!$path || !is_file($path)) {
      $this->fail(404);
    }
    Storage::send_file($path, wp_basename($path), true);
  }

  private function send_resource(int $lesson_id, int $attachment_id): never {
    $ids = array_map('intval', (array) get_post_meta($lesson_id, '_nba_resources', true));
    if (!$attachment_id || !in_array($attachment_id, $ids, true)) {
      $this->fail(404);
    }
    $path = get_attached_file($attachment_id);
    if (!$path || !is_file($path)) {
      $this->fail(404);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    Storage::send_file($path, wp_basename($path), in_array($ext, ['pdf', 'mp3', 'm4a'], true));
  }

  // ------------------------------------------------------------ admin helpers

  /**
   * @return array{ok: bool, message: string}
   */
  public function hls_status(int $media_id): array {
    $folder = (string) get_post_meta($media_id, '_nba_path', true);
    if ($folder === '') {
      return ['ok' => false, 'message' => __('No folder set. Use Academy → Import Videos.', 'nirog-academy')];
    }
    $dir = $this->hls_dir($media_id);
    if (!$dir) {
      return ['ok' => false, 'message' => __('Folder not found on the server.', 'nirog-academy')];
    }
    if (!is_file($dir . '/master.m3u8')) {
      return ['ok' => false, 'message' => __('master.m3u8 is missing in the folder.', 'nirog-academy')];
    }
    $key = (string) get_post_meta($media_id, '_nba_key_file', true);
    $key_path = $key ? Storage::resolve('keys', $key) : null;
    if (!$key_path || filesize($key_path) !== 16) {
      return ['ok' => false, 'message' => __('Encryption key is missing. Re-run Import Videos.', 'nirog-academy')];
    }
    return ['ok' => true, 'message' => __('Ready', 'nirog-academy')];
  }
}
