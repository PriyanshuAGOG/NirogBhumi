<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * English and Hindi.
 *
 * Interface text: standard WordPress translations. Hindi strings ship in
 * languages/nirog-academy-hi_IN.l10n.php (the PHP translation format that
 * WordPress 6.5+ loads directly).
 *
 * Content: programmes and lessons have optional Hindi title, summary and
 * safety note fields. When the participant chooses Hindi and a Hindi field is
 * filled in, it is shown; otherwise the English text is shown.
 */
final class I18n {
  public const LOCALES = ['en_US' => 'English', 'hi_IN' => 'हिन्दी'];
  public const USER_META = 'nba_locale';
  public const COOKIE = 'nba_lang';

  public static function register(): void {
    add_action('init', [self::class, 'load_textdomain'], 1);
    add_filter('determine_locale', [self::class, 'determine_locale'], 20);
  }

  public static function load_textdomain(): void {
    load_plugin_textdomain('nirog-academy', false, dirname(plugin_basename(NBA_FILE)) . '/languages');
  }

  /**
   * Apply the participant's language only inside the Academy, so the rest of
   * the website keeps its own language.
   */
  public static function determine_locale(string $locale): string {
    if (is_admin() && !wp_doing_ajax()) {
      return $locale;
    }
    if (!self::is_academy_request()) {
      return $locale;
    }
    $chosen = self::chosen_locale();
    return $chosen ?: $locale;
  }

  public static function chosen_locale(int $user_id = 0): string {
    $user_id = $user_id ?: (function_exists('get_current_user_id') && did_action('set_current_user') ? get_current_user_id() : 0);
    if ($user_id) {
      $meta = (string) get_user_meta($user_id, self::USER_META, true);
      if (isset(self::LOCALES[$meta])) {
        return $meta;
      }
    }
    $cookie = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : '';
    return isset(self::LOCALES[$cookie]) ? $cookie : '';
  }

  public static function is_academy_request(): bool {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) wp_parse_url($uri, PHP_URL_PATH);
    $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    if ($home_path && $home_path !== '/' && str_starts_with($path, $home_path)) {
      $path = '/' . ltrim(substr($path, strlen($home_path)), '/');
    }
    $settings = get_option(Settings::OPTION, []);
    $slug = is_array($settings) && !empty($settings['base_slug']) ? $settings['base_slug'] : 'academy';
    return str_starts_with($path, '/' . $slug . '/') || $path === '/' . $slug
      || str_contains($path, '/wp-json/nirog-academy/')
      || (isset($_GET['rest_route']) && str_starts_with((string) $_GET['rest_route'], '/nirog-academy/'));
  }

  /**
   * Localised content field with English fallback.
   *
   * @param string $field title|summary|safety_note|ack_text
   */
  public static function field(int $post_id, string $field): string {
    $english = match ($field) {
      'title' => get_the_title($post_id),
      'summary' => (string) get_post_meta($post_id, '_nba_summary', true),
      default => (string) get_post_meta($post_id, '_nba_' . $field, true),
    };
    if (self::current() === 'hi_IN') {
      $hindi = (string) get_post_meta($post_id, '_nba_' . $field . '_hi', true);
      if (trim($hindi) !== '') {
        return $hindi;
      }
    }
    return $english;
  }

  public static function current(): string {
    $locale = is_locale_switched() ? get_locale() : determine_locale();
    return $locale === 'hi_IN' ? 'hi_IN' : 'en_US';
  }

  public static function set_user_locale(int $user_id, string $locale): void {
    if (!isset(self::LOCALES[$locale])) {
      return;
    }
    if ($user_id) {
      update_user_meta($user_id, self::USER_META, $locale);
    }
    setcookie(self::COOKIE, $locale, [
      'expires' => time() + YEAR_IN_SECONDS,
      'path' => COOKIEPATH ?: '/',
      'domain' => COOKIE_DOMAIN ?: '',
      'secure' => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    $_COOKIE[self::COOKIE] = $locale;
  }

  /**
   * Run a callback in the participant's language, for emails.
   */
  public static function with_user_locale(int $user_id, callable $callback): mixed {
    $locale = self::chosen_locale($user_id) ?: 'en_US';
    $switched = switch_to_locale($locale);
    try {
      return $callback();
    } finally {
      if ($switched) {
        restore_previous_locale();
      }
    }
  }
}
