<?php
namespace NirogBhumi\Academy\Frontend;

defined('ABSPATH') || exit;

/**
 * Template rendering and small presentation helpers.
 *
 * Templates can be overridden by copying them from the plugin's templates/
 * folder into {theme}/nirog-academy/.
 */
final class View {
  public static function locate(string $name): string {
    $name = implode('/', array_map('sanitize_file_name', explode('/', $name)));
    $theme = locate_template('nirog-academy/' . $name . '.php');
    return $theme ?: NBA_DIR . 'templates/' . $name . '.php';
  }

  /**
   * Render a template to a string.
   */
  public static function capture(string $name, array $vars = []): string {
    $file = self::locate($name);
    if (!is_readable($file)) {
      return '';
    }
    ob_start();
    (static function (string $__file, array $__vars): void {
      extract($__vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
      include $__file;
    })($file, $vars);
    return (string) ob_get_clean();
  }

  /**
   * Print a partial template (templates/parts/…).
   */
  public static function include(string $name, array $vars = []): void {
    echo self::capture($name, $vars); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their own output.
  }

  /**
   * A week's display title without a repeated "Week 3:" prefix, because the
   * week number is already shown next to it. Falls back to "Week 3".
   */
  public static function week_title(int $module_id, int $index): string {
    $title = $module_id ? \NirogBhumi\Academy\Support\I18n::field($module_id, 'title') : '';
    $clean = trim((string) preg_replace('/^\s*(week|सप्ताह|हफ़्ता|हफ्ता)\s*\d+\s*[:\-–—·.]?\s*/iu', '', $title));
    /* translators: %d: week number */
    return $clean !== '' ? $clean : sprintf(__('Week %d', 'nirog-academy'), $index);
  }

  /** @return array<string, string> Item type → label shown to participants. */
  public static function type_labels(): array {
    return [
      'video' => __('Video', 'nirog-academy'),
      'audio' => __('Audio', 'nirog-academy'),
      'booklet' => __('Booklet', 'nirog-academy'),
      'reading' => __('Reading', 'nirog-academy'),
    ];
  }

  /**
   * Render a full Academy page: the shell (header, navigation) around a view.
   */
  public static function page(string $view, array $vars = [], array $shell = []): never {
    $content = self::capture($view, $vars);
    $shell = array_merge([
      'title' => '',
      'view' => $view,
      'content' => $content,
      'bare' => false, // true = no navigation (login, set password)
    ], $shell);
    self::send_headers();
    echo self::capture('layout', $shell); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their own output.
    exit;
  }

  public static function send_headers(): void {
    if (headers_sent()) {
      return;
    }
    nocache_headers();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  }

  /**
   * Inline SVG icons (no icon font, no external requests).
   */
  public static function icon(string $name, int $size = 20): string {
    $paths = [
      'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
      'book' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H20v16H5.5A1.5 1.5 0 0 0 4 20.5zM4 20.5A1.5 1.5 0 0 0 5.5 22H20"/>',
      'gift' => '<path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/>',
      'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
      'live' => '<circle cx="12" cy="12" r="3"/><path d="M6.3 6.3a8 8 0 0 0 0 11.4M17.7 6.3a8 8 0 0 1 0 11.4"/>',
      'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
      'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
      'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
      'check' => '<path d="M20 6 9 17l-5-5"/>',
      'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
      'play' => '<path d="M7 4v16l13-8z"/>',
      'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
      'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
      'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
      'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
      'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
      'device' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
      'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
      'audio' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3zM3 19a2 2 0 0 0 2 2h1v-6H3z"/>',
      'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
      'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
    ];
    $path = $paths[$name] ?? $paths['help'];
    return '<svg class="nba-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
  }

  /**
   * Circular progress indicator.
   */
  public static function ring(int $pct, int $size = 96, string $label = ''): string {
    $pct = max(0, min(100, $pct));
    $r = 42;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - $pct / 100);
    return '<div class="nba-ring" style="width:' . $size . 'px;height:' . $size . 'px" role="img" aria-label="' . esc_attr($label ?: sprintf(
      /* translators: %d: percent */
      __('%d%% complete', 'nirog-academy'),
      $pct
    )) . '"><svg viewBox="0 0 100 100"><circle class="nba-ring-track" cx="50" cy="50" r="' . $r . '"/><circle class="nba-ring-fill" cx="50" cy="50" r="' . $r . '" stroke-dasharray="' . round($c, 2) . '" stroke-dashoffset="' . round($offset, 2) . '"/></svg><span>' . $pct . '%</span></div>';
  }

  public static function minutes(int $minutes): string {
    if ($minutes <= 0) {
      return '';
    }
    /* translators: %d: minutes */
    return sprintf(_n('%d min', '%d min', $minutes, 'nirog-academy'), $minutes);
  }

  public static function logo_url(): string {
    $custom = (int) get_theme_mod('custom_logo');
    $url = $custom ? (string) wp_get_attachment_image_url($custom, 'medium') : '';
    if (!$url && file_exists(get_stylesheet_directory() . '/assets/img/nirog-bhumi-logo.png')) {
      $url = get_stylesheet_directory_uri() . '/assets/img/nirog-bhumi-logo.png';
    }
    return (string) apply_filters('nba_logo_url', $url);
  }

  public static function lang_switch_url(string $locale): string {
    return wp_nonce_url(add_query_arg('nba_lang', $locale), 'nba_lang', '_nbalang');
  }
}
