<?php
namespace NirogBhumi\Academy\Frontend;

use NirogBhumi\Academy\Notify\Notifications;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

/**
 * Form submissions in the participant area. Every form carries a nonce for
 * its action; every handler redirects afterwards (no resubmission on reload).
 */
final class Actions {
  private const FLASH_COOKIE = 'nba_flash';

  public static function handle(string $view): void {
    $action = isset($_POST['nba_action']) ? sanitize_key(wp_unslash($_POST['nba_action'])) : '';
    if ($action === '' || !isset($_POST['_nbanonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nbanonce'])), 'nba_' . $action)) {
      self::redirect_with('error', __('This form has expired. Please try again.', 'nirog-academy'), Router::url($view === 'dashboard' ? '' : $view));
    }
    $public = ['login', 'magic', 'forgot', 'set_password'];
    if (!in_array($action, $public, true) && !is_user_logged_in()) {
      wp_safe_redirect(Router::url('login'));
      exit;
    }
    match ($action) {
      'login' => Auth::handle_login(),
      'magic' => Auth::handle_magic_request(),
      'forgot' => Auth::handle_forgot(),
      'set_password' => Auth::handle_set_password(),
      'ack' => self::acknowledge(),
      'device_code' => self::device_code(),
      'device_confirm' => self::device_confirm(),
      'device_request' => self::device_request(),
      'profile' => self::profile(),
      default => self::redirect_with('error', __('Unknown action.', 'nirog-academy'), Router::url()),
    };
  }

  // ------------------------------------------------------------ flash messages

  public static function redirect_with(string $type, string $message, string $url): never {
    $flash = [$type => $message];
    if (!headers_sent()) {
      setcookie(self::FLASH_COOKIE, base64_encode((string) wp_json_encode($flash)), [
        'expires' => time() + 120,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    }
    wp_safe_redirect($url);
    exit;
  }

  /** @var array<string, string>|null */
  private static ?array $flash = null;

  public static function flash(string $type): string {
    if (self::$flash === null) {
      self::$flash = [];
      if (!empty($_COOKIE[self::FLASH_COOKIE])) {
        $decoded = json_decode((string) base64_decode(sanitize_text_field(wp_unslash($_COOKIE[self::FLASH_COOKIE])), true), true);
        self::$flash = is_array($decoded) ? array_map('strval', $decoded) : [];
        if (!headers_sent()) {
          setcookie(self::FLASH_COOKIE, '', ['expires' => time() - 3600, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '']);
        }
      }
    }
    return isset(self::$flash[$type]) ? sanitize_text_field(self::$flash[$type]) : '';
  }

  // ------------------------------------------------------------ handlers

  private static function acknowledge(): never {
    $user_id = get_current_user_id();
    $programme_id = isset($_POST['programme_id']) ? absint($_POST['programme_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
    $next = isset($_POST['next']) ? absint($_POST['next']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
    if (empty($_POST['accept'])) { // phpcs:ignore WordPress.Security.NonceVerification
      self::redirect_with('error', __('Please tick the box to confirm you have read the note.', 'nirog-academy'), add_query_arg('next', $next, Router::url('orientation/' . get_post_field('post_name', $programme_id))));
    }
    if (nba()->gate->check_programme($user_id, $programme_id)->allowed) {
      nba()->gate->acknowledge($user_id, $programme_id);
    }
    wp_safe_redirect($next ? Router::lesson_url($next) : Router::programme_url($programme_id));
    exit;
  }

  private static function device_code(): never {
    $user_id = get_current_user_id();
    if (!nba()->devices->can_self_reset($user_id)) {
      self::redirect_with('error', __('You changed device recently, so this change needs our team. Send a request below.', 'nirog-academy'), Router::url('device'));
    }
    $result = nba()->devices->send_code($user_id);
    if (is_wp_error($result)) {
      self::redirect_with('error', $result->get_error_message(), Router::url('device'));
    }
    self::redirect_with('notice', __('We have emailed you a 6-digit code. Enter it below.', 'nirog-academy'), Router::url('device'));
  }

  private static function device_confirm(): never {
    $user_id = get_current_user_id();
    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $result = nba()->devices->confirm_code($user_id, $code);
    if (is_wp_error($result)) {
      if ($result->get_error_code() === 'nba_limit') {
        nba()->devices->create_request($user_id, 'Self-service limit reached');
      }
      self::redirect_with('error', $result->get_error_message(), Router::url('device'));
    }
    self::redirect_with('notice', __('Done. This is now your registered device.', 'nirog-academy'), Router::url());
  }

  private static function device_request(): never {
    $user_id = get_current_user_id();
    $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    nba()->devices->create_request($user_id, $reason);
    self::redirect_with('notice', __('Request sent. Our team will reply by email, usually within one working day.', 'nirog-academy'), Router::url('device'));
  }

  private static function profile(): never {
    $user_id = get_current_user_id();
    // phpcs:disable WordPress.Security.NonceVerification -- verified in handle().
    $first = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $phone = isset($_POST['phone']) ? preg_replace('/[^\d+ ]/', '', sanitize_text_field(wp_unslash($_POST['phone']))) : '';
    $locale = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : '';
    $prefs = [
      'lessons' => empty($_POST['pref_lessons']) ? 0 : 1,
      'reminders' => empty($_POST['pref_reminders']) ? 0 : 1,
      'announcements' => empty($_POST['pref_announcements']) ? 0 : 1,
    ];
    // phpcs:enable
    $update = ['ID' => $user_id];
    if ($first !== '') {
      $update['first_name'] = $first;
      $update['last_name'] = $last;
      $update['display_name'] = trim($first . ' ' . $last);
    }
    wp_update_user($update);
    if ($phone !== '') {
      update_user_meta($user_id, 'billing_phone', $phone);
    }
    update_user_meta($user_id, Notifications::PREFS_META, $prefs);
    if ($locale) {
      I18n::set_user_locale($user_id, $locale);
    }
    self::redirect_with('notice', __('Your profile is saved.', 'nirog-academy'), Router::url('profile'));
  }
}
